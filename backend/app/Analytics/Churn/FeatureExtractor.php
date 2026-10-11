<?php

namespace App\Analytics\Churn;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds CustomerFeatures for every active customer as of a date, looking only
 * at visits on or before that date — which is what makes honest backtests
 * possible (no information from the future leaks into a past score).
 *
 * Two queries in total: one streamed pass over visits (for gaps and windowed
 * counts) and one aggregate for each customer's most frequent service.
 */
class FeatureExtractor
{
    private const DEFAULT_INTERVAL = 45;

    /** @return Collection<int, CustomerFeatures> keyed by customer id */
    public function extract(CarbonImmutable $asOf): Collection
    {
        $config = config('retention');
        $periodStart = $asOf->subDays($config['rfm_period_days']);
        $activeSince = $asOf->subDays($config['active_window_days']);
        $minVisits = $config['churn']['min_visits_for_personal_interval'];
        $fallback = $this->fallbackIntervals($asOf);

        $features = collect();
        $current = null;
        $dates = [];
        $spend = 0.0;

        $flush = function () use (&$current, &$dates, &$spend, &$features, $asOf, $periodStart, $activeSince, $minVisits, $fallback) {
            if ($current === null || $dates === []) {
                return;
            }
            $last = end($dates);
            if ($last->lt($activeSince)) {
                return;
            }

            $gaps = [];
            for ($i = 1, $n = count($dates); $i < $n; $i++) {
                $gaps[] = $dates[$i - 1]->diffInDays($dates[$i]);
            }

            $expected = count($dates) >= $minVisits
                ? (int) max(7, round($this->median($gaps)))
                : ($fallback[$current] ?? self::DEFAULT_INTERVAL);

            $features[$current] = new CustomerFeatures(
                customerId: $current,
                lastVisit: $last,
                daysSinceLastVisit: (int) $last->diffInDays($asOf),
                totalVisits: count($dates),
                visitsInPeriod: count(array_filter($dates, fn ($d) => $d->gt($periodStart))),
                spendInPeriod: round($spend, 2),
                visitsLast180: count(array_filter($dates, fn ($d) => $d->gt($asOf->subDays(180)))),
                visitsPrior180: count(array_filter($dates, fn ($d) => $d->gt($asOf->subDays(360)) && $d->lte($asOf->subDays(180)))),
                expectedIntervalDays: $expected,
            );
        };

        DB::table('visits')
            ->where('visited_on', '<=', $asOf->toDateString())
            ->orderBy('customer_id')
            ->orderBy('visited_on')
            ->select('customer_id', 'visited_on', 'total')
            // cursor(): one streamed query. lazy() would paginate with OFFSET, which
            // can skip or repeat rows when the sort key is not unique.
            ->cursor()
            ->each(function ($visit) use (&$current, &$dates, &$spend, $flush, $periodStart) {
                if ((int) $visit->customer_id !== $current) {
                    $flush();
                    $current = (int) $visit->customer_id;
                    $dates = [];
                    $spend = 0.0;
                }
                $date = CarbonImmutable::parse($visit->visited_on);
                $dates[] = $date;
                if ($date->gt($periodStart)) {
                    $spend += (float) $visit->total;
                }
            });
        $flush();

        return $features;
    }

    /**
     * Rhythm for customers with too few visits: the typical repeat interval of
     * the service they bought most often.
     *
     * @return array<int, int> customer id → days
     */
    private function fallbackIntervals(CarbonImmutable $asOf): array
    {
        return DB::table('visit_services')
            ->join('visits', 'visits.id', '=', 'visit_services.visit_id')
            ->join('services', 'services.id', '=', 'visit_services.service_id')
            ->where('visits.visited_on', '<=', $asOf->toDateString())
            ->select('visits.customer_id', 'services.typical_interval_days', DB::raw('count(*) as uses'))
            ->groupBy('visits.customer_id', 'services.typical_interval_days')
            ->orderBy('visits.customer_id')
            ->orderByDesc('uses')
            ->orderBy('services.typical_interval_days')
            ->get()
            ->unique('customer_id')
            ->mapWithKeys(fn ($row) => [$row->customer_id => (int) $row->typical_interval_days])
            ->all();
    }

    private function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
