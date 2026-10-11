<?php

use App\Analytics\AnalysisService;
use App\Analytics\Backtest\Backtester;
use App\Analytics\SegmentMigration;
use App\Analytics\Synthetic\SyntheticDataGenerator;
use App\Enums\Segment;
use App\Models\Customer;
use App\Models\CustomerScore;
use Carbon\CarbonImmutable;

it('stores a scored snapshot with an explainable risk for every active customer', function () {
    $loyal = customerVisitedDaysAgo(regularVisits(330, 10, 20), total: 60);
    $lapsing = customerVisitedDaysAgo(regularVisits(330, 120, 20), total: 60);
    customerVisitedDaysAgo([5], total: 30);

    $run = app(AnalysisService::class)->run(CarbonImmutable::today());

    $loyalScore = CustomerScore::where('analysis_run_id', $run->id)->where('customer_id', $loyal->id)->sole();
    $lapsingScore = CustomerScore::where('analysis_run_id', $run->id)->where('customer_id', $lapsing->id)->sole();

    // Quintiles over three customers are coarse: "recent" may land on R=3 or higher.
    expect($run->scores()->count())->toBe(3)
        ->and($loyalScore->segment)->toBeIn([Segment::Champions, Segment::Loyal])
        ->and($lapsingScore->segment)->toBeIn([Segment::AtRisk, Segment::CantLoseThem])
        ->and($lapsingScore->churn_risk)->toBeGreaterThan($loyalScore->churn_risk + 40)
        ->and($lapsingScore->risk_factors[0]['key'])->toBe('overdue')
        ->and($lapsingScore->revenue_at_risk)->toBeGreaterThan(0)
        ->and($loyalScore->next_visit_due->toDateString())->toBe(CarbonImmutable::today()->addDays(10)->toDateString());
});

it('summarises segments, revenue and the risk histogram', function () {
    customerVisitedDaysAgo(regularVisits(300, 10, 30), total: 50);
    customerVisitedDaysAgo([250], total: 40);

    $summary = app(AnalysisService::class)->run(CarbonImmutable::today())->summary;

    expect($summary['customers'])->toBe(2)
        ->and(array_sum($summary['risk_histogram']))->toBe(2)
        ->and(array_sum(array_column($summary['segments'], 'customers')))->toBe(2)
        ->and(round(array_sum(array_column($summary['segments'], 'revenue_share')), 2))->toBe(1.0)
        ->and($summary['params'] ?? null)->toBeNull();
});

it('records the RFM thresholds used, so a snapshot can be explained later', function () {
    customerVisitedDaysAgo([10, 40]);
    $run = app(AnalysisService::class)->run(CarbonImmutable::today());

    expect($run->params)->toHaveKeys(['recency_quintiles', 'monetary_quintiles', 'frequency_bins', 'churn']);
});

it('shows how customers moved between segments', function () {
    // Recency is scored relative to the base, so give it steady regulars to compare with.
    foreach ([4, 9, 13, 17] as $offset) {
        customerVisitedDaysAgo(regularVisits(330 + $offset, $offset, 20));
    }
    $customer = customerVisitedDaysAgo(regularVisits(330, 70, 20)); // stopped coming 70 days ago

    $first = app(AnalysisService::class)->run(CarbonImmutable::today()->subDays(60));
    $second = app(AnalysisService::class)->run(CarbonImmutable::today());

    $score = fn ($run) => CustomerScore::where('analysis_run_id', $run->id)->where('customer_id', $customer->id)->value('segment');
    $from = $score($first);
    $to = $score($second);
    $migration = app(SegmentMigration::class)->between($first, $second);

    expect($from)->toBeIn([Segment::Champions, Segment::Loyal])
        ->and($to)->toBeIn([Segment::AtRisk, Segment::CantLoseThem])
        ->and($migration['matrix'][$from->value][$to->value])->toBeGreaterThanOrEqual(1)
        ->and(array_sum(array_map('array_sum', $migration['matrix'])))->toBe(5);
});

it('backtests the model against what actually happened and beats the recency baseline', function () {
    app(SyntheticDataGenerator::class)->generate(400, CarbonImmutable::today(), seed: 7);

    $result = app(Backtester::class)->run(CarbonImmutable::today(), 90)->summary['backtest'];

    expect($result['cutoff'])->toBe('2025-12-02')
        ->and($result['customers'])->toBeGreaterThan(150)
        ->and($result['auc'])->toBeGreaterThan(0.75)
        ->and($result['auc'])->toBeGreaterThanOrEqual($result['baseline_recency_auc'])
        ->and($result['deciles'])->toHaveCount(10);

    // Calibration: the riskiest decile with data churns more than the safest one.
    $rates = array_values(array_filter(array_column($result['deciles'], 'actual_rate'), fn ($r) => $r !== null));
    expect(end($rates))->toBeGreaterThan($rates[0]);
});

it('generates a reproducible synthetic base', function () {
    $generator = app(SyntheticDataGenerator::class);
    $generator->generate(50, CarbonImmutable::today(), seed: 11);
    $first = Customer::orderBy('id')->pluck('synthetic_profile')->all();

    Customer::query()->delete();
    $generator->generate(50, CarbonImmutable::today(), seed: 11);

    expect(Customer::orderBy('id')->pluck('synthetic_profile')->all())->toBe($first)
        ->and(Customer::where('email', 'not like', '%@example.test')->exists())->toBeFalse();
});
