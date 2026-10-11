<?php

namespace App\Analytics;

use App\Analytics\Churn\ChurnRiskModel;
use App\Analytics\Churn\CustomerFeatures;
use App\Analytics\Churn\FeatureExtractor;
use App\Analytics\Rfm\RfmScorer;
use App\Enums\Segment;
use App\Models\AnalysisRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One analysis run = features → RFM scores → segment → churn risk for every
 * active customer, stored as an immutable snapshot (customer_scores). Keeping
 * snapshots instead of overwriting lets us show segment migration between runs
 * and replay the model at past dates for backtesting.
 */
class AnalysisService
{
    public function __construct(
        private readonly FeatureExtractor $features,
        private readonly ChurnRiskModel $churn,
    ) {}

    public function run(CarbonImmutable $asOf, string $kind = 'live'): AnalysisRun
    {
        $features = $this->features->extract($asOf);

        $recencyCuts = RfmScorer::quintiles($features->pluck('daysSinceLastVisit')->all());
        $monetaryCuts = RfmScorer::quintiles($features->pluck('spendInPeriod')->all());

        return DB::transaction(function () use ($asOf, $kind, $features, $recencyCuts, $monetaryCuts) {
            $run = AnalysisRun::create([
                'as_of' => $asOf->toDateString(),
                'kind' => $kind,
                'params' => [
                    'rfm_period_days' => config('retention.rfm_period_days'),
                    'recency_quintiles' => $recencyCuts,
                    'monetary_quintiles' => $monetaryCuts,
                    'frequency_bins' => RfmScorer::FREQUENCY_BINS,
                    'churn' => config('retention.churn'),
                ],
            ]);

            $rows = $features->map(fn (CustomerFeatures $f) => $this->scoreRow($run, $f, $recencyCuts, $monetaryCuts));

            foreach ($rows->chunk(500) as $chunk) {
                DB::table('customer_scores')->insert($chunk->values()->all());
            }

            $run->update(['summary' => $this->summarize($rows)]);

            return $run;
        });
    }

    private function scoreRow(AnalysisRun $run, CustomerFeatures $f, array $recencyCuts, array $monetaryCuts): array
    {
        $r = RfmScorer::scoreDescending($f->daysSinceLastVisit, $recencyCuts);
        $fr = RfmScorer::frequencyScore($f->visitsInPeriod);
        $m = RfmScorer::scoreAscending($f->spendInPeriod, $monetaryCuts);
        $risk = $this->churn->score($f);

        return [
            'analysis_run_id' => $run->id,
            'customer_id' => $f->customerId,
            'recency_days' => $f->daysSinceLastVisit,
            'frequency' => $f->visitsInPeriod,
            'monetary' => $f->spendInPeriod,
            'r' => $r,
            'f' => $fr,
            'm' => $m,
            'segment' => Segment::classify($r, $fr)->value,
            'churn_risk' => $risk['risk'],
            'risk_factors' => json_encode($risk['factors']),
            'expected_interval_days' => $f->expectedIntervalDays,
            'next_visit_due' => $f->lastVisit->addDays($f->expectedIntervalDays)->toDateString(),
            // Annual value (spend in the period) weighted by the probability of losing it.
            'revenue_at_risk' => round($f->spendInPeriod * $risk['risk'] / 100, 2),
        ];
    }

    private function summarize(Collection $rows): array
    {
        $totalRevenue = max(0.01, $rows->sum('monetary'));

        $segments = collect(Segment::cases())->mapWithKeys(function (Segment $segment) use ($rows, $totalRevenue) {
            $members = $rows->where('segment', $segment->value);

            return [$segment->value => [
                'label' => $segment->label(),
                'customers' => $members->count(),
                'revenue' => round($members->sum('monetary'), 2),
                'revenue_share' => round($members->sum('monetary') / $totalRevenue, 4),
                'avg_churn_risk' => $members->isEmpty() ? null : (int) round($members->avg('churn_risk')),
                'revenue_at_risk' => round($members->sum('revenue_at_risk'), 2),
            ]];
        });

        $histogram = array_fill(0, 10, 0);
        foreach ($rows as $row) {
            $histogram[min(9, intdiv($row['churn_risk'], 10))]++;
        }

        return [
            'customers' => $rows->count(),
            'revenue' => round($rows->sum('monetary'), 2),
            'revenue_at_risk' => round($rows->sum('revenue_at_risk'), 2),
            'high_risk_customers' => $rows->where('churn_risk', '>=', 70)->count(),
            'segments' => $segments->all(),
            'risk_histogram' => $histogram,
        ];
    }
}
