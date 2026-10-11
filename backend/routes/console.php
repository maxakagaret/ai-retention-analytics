<?php

use App\Analytics\AnalysisService;
use App\Analytics\Backtest\Backtester;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('retention:analyze {--as-of= : Date to score at (default today)}', function (AnalysisService $analysis) {
    $asOf = $this->option('as-of') ? CarbonImmutable::parse($this->option('as-of')) : CarbonImmutable::today();
    $run = $analysis->run($asOf);

    $this->info("Run #{$run->id} as of {$asOf->toDateString()}: {$run->summary['customers']} customers, "
        ."{$run->summary['high_risk_customers']} high-risk, \${$run->summary['revenue_at_risk']} revenue at risk.");
})->purpose('Score every active customer (RFM + churn risk) and store a snapshot');

Artisan::command('retention:backtest {--horizon=90 : Days to look ahead after the cutoff}', function (Backtester $backtester) {
    $result = $backtester->run(horizonDays: (int) $this->option('horizon'))->summary['backtest'];

    $this->info("Cutoff {$result['cutoff']}, horizon {$result['horizon_days']}d, {$result['customers']} customers, churn rate {$result['churn_rate']}");
    $this->info("ROC AUC: model {$result['auc']} vs recency-only baseline {$result['baseline_recency_auc']}; precision@100 {$result['precision_at_100']}");
    $this->table(['risk decile', 'customers', 'actual churn rate'], array_map(fn ($d) => array_values($d), $result['deciles']));
})->purpose('Replay the churn model at a past date and measure it against what happened');

// Nightly snapshot keeps the segment-migration view and the dashboards fresh.
Schedule::command('retention:analyze')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('retention:backtest')->weeklyOn(1, '04:00')->withoutOverlapping();
