<?php

namespace App\Analytics\Backtest;

use App\Analytics\AnalysisService;
use App\Models\AnalysisRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Measures the churn model instead of trusting it.
 *
 * Scores are computed as of (today − horizon) using only the visits known at
 * that moment, then compared with what actually happened next: a customer
 * "churned" if they made no visit during the horizon. We report:
 *   - ROC AUC of the risk score, and of plain recency as a naive baseline
 *     (the model must beat "longest absence first" to be worth anything);
 *   - calibration: actual churn rate per risk decile;
 *   - precision of the top-100 highest-risk list (what a call centre would use).
 */
class Backtester
{
    public function __construct(private readonly AnalysisService $analysis) {}

    public function run(?CarbonImmutable $today = null, ?int $horizonDays = null): AnalysisRun
    {
        $today ??= CarbonImmutable::today();
        $horizonDays ??= (int) config('retention.churn.horizon_days');
        $cutoff = $today->subDays($horizonDays);

        $run = $this->analysis->run($cutoff, 'backtest');

        $returned = DB::table('visits')
            ->where('visited_on', '>', $cutoff->toDateString())
            ->where('visited_on', '<=', $today->toDateString())
            ->distinct()
            ->pluck('customer_id')
            ->flip();

        $rows = DB::table('customer_scores')
            ->where('analysis_run_id', $run->id)
            ->get(['customer_id', 'churn_risk', 'recency_days'])
            ->map(fn ($row) => [
                'risk' => (int) $row->churn_risk,
                'recency' => (int) $row->recency_days,
                'churned' => ! $returned->has($row->customer_id),
            ]);

        $labels = $rows->pluck('churned')->all();

        $run->update(['summary' => [
            ...$run->summary,
            'backtest' => [
                'cutoff' => $cutoff->toDateString(),
                'horizon_days' => $horizonDays,
                'customers' => $rows->count(),
                'churn_rate' => $rows->count() ? round($rows->where('churned', true)->count() / $rows->count(), 4) : null,
                'auc' => Metrics::rocAuc($rows->pluck('risk')->all(), $labels),
                'baseline_recency_auc' => Metrics::rocAuc($rows->pluck('recency')->all(), $labels),
                'deciles' => Metrics::deciles($rows->pluck('risk')->all(), $labels),
                'precision_at_100' => Metrics::precisionAtK($rows->pluck('risk')->all(), $labels, 100),
            ],
        ]]);

        return $run->refresh();
    }
}
