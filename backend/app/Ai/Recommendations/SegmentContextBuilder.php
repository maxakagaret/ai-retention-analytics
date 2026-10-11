<?php

namespace App\Ai\Recommendations;

use App\Enums\Segment;
use App\Models\AnalysisRun;
use Illuminate\Support\Facades\DB;

/**
 * The only data the model ever sees: segment-level aggregates. No names, no
 * emails, no customer ids — recommendations are about the segment, and personal
 * data has no business leaving the system (a test enforces this).
 */
class SegmentContextBuilder
{
    public function build(AnalysisRun $run, Segment $segment): array
    {
        $scores = DB::table('customer_scores')->where('analysis_run_id', $run->id)->where('segment', $segment->value);

        $agg = (clone $scores)->selectRaw('count(*) as customers, avg(recency_days) as recency, avg(frequency) as frequency,
            avg(monetary) as monetary, avg(churn_risk) as risk, avg(expected_interval_days) as rhythm,
            sum(revenue_at_risk) as at_risk, sum(monetary) as revenue')->first();

        $topServices = DB::table('visit_services')
            ->join('visits', 'visits.id', '=', 'visit_services.visit_id')
            ->join('services', 'services.id', '=', 'visit_services.service_id')
            ->whereIn('visits.customer_id', (clone $scores)->select('customer_id'))
            ->where('visits.visited_on', '<=', $run->as_of->toDateString())
            ->select('services.name', 'services.category', DB::raw('count(*) as uses'))
            ->groupBy('services.name', 'services.category')
            ->orderByDesc('uses')
            ->limit(4)
            ->get()
            ->map(fn ($s) => ['service' => $s->name, 'category' => $s->category, 'share_of_visits' => (int) $s->uses])
            ->all();

        $totalUses = max(1, array_sum(array_column($topServices, 'share_of_visits')));
        foreach ($topServices as &$service) {
            $service['share_of_visits'] = round($service['share_of_visits'] / $totalUses, 2);
        }

        return [
            'segment' => $segment->value,
            'segment_label' => $segment->label(),
            'goal' => $segment->goal(),
            'as_of' => $run->as_of->toDateString(),
            'customers' => (int) $agg->customers,
            'share_of_base' => round((int) $agg->customers / max(1, (int) ($run->summary['customers'] ?? 1)), 3),
            'avg_days_since_last_visit' => (int) round((float) $agg->recency),
            'avg_visits_per_year' => round((float) $agg->frequency, 1),
            'avg_annual_spend' => round((float) $agg->monetary, 2),
            'avg_usual_interval_days' => (int) round((float) $agg->rhythm),
            'avg_churn_risk' => (int) round((float) $agg->risk),
            'revenue_last_year' => round((float) $agg->revenue, 2),
            'revenue_at_risk' => round((float) $agg->at_risk, 2),
            'top_services' => $topServices,
            'available_channels' => ['sms', 'email', 'messenger', 'call'],
        ];
    }
}
