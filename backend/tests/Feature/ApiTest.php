<?php

use App\Analytics\AnalysisService;
use App\Jobs\GenerateRecommendation;
use App\Models\AnalysisRun;
use App\Models\Recommendation;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Queue;

it('runs an analysis and returns the overview', function () {
    customerVisitedDaysAgo(regularVisits(300, 10, 30));

    $this->postJson('/api/analysis/runs')->assertCreated()->assertJsonPath('data.summary.customers', 1);

    $this->getJson('/api/overview')
        ->assertOk()
        ->assertJsonPath('data.run.summary.customers', 1)
        ->assertJsonPath('data.backtest', null);
});

it('rejects analysis dates in the future', function () {
    $this->postJson('/api/analysis/runs', ['as_of' => '2030-01-01'])->assertUnprocessable();
});

it('lists segments and their customers sorted by risk', function () {
    customerVisitedDaysAgo(regularVisits(330, 120, 20));
    customerVisitedDaysAgo(regularVisits(330, 100, 20));
    app(AnalysisService::class)->run(CarbonImmutable::today());

    $segment = collect($this->getJson('/api/segments')->assertOk()->json('data'))->firstWhere('customers', '>', 0);
    $rows = $this->getJson("/api/segments/{$segment['key']}/customers")->assertOk()->json('data');

    expect($rows[0]['churn_risk'])->toBeGreaterThanOrEqual(end($rows)['churn_risk'])
        ->and($rows[0]['top_factor'])->toContain('days since the last visit');
});

it('shows a customer profile with score history', function () {
    $customer = customerVisitedDaysAgo([90, 60, 30]);
    app(AnalysisService::class)->run(CarbonImmutable::today()->subDays(15));
    app(AnalysisService::class)->run(CarbonImmutable::today());

    $this->getJson("/api/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.history')
        ->assertJsonCount(3, 'data.visits')
        ->assertJsonPath('data.score.expected_interval_days', 30);
});

it('needs two runs for the migration matrix', function () {
    $this->getJson('/api/analysis/migration')->assertUnprocessable();
});

it('queues recommendation generation instead of blocking the request', function () {
    Queue::fake();
    customerVisitedDaysAgo([10, 40]);
    $segment = app(AnalysisService::class)->run(CarbonImmutable::today())->scores()->value('segment');

    $this->postJson("/api/segments/{$segment->value}/recommendations")
        ->assertAccepted()
        ->assertJsonPath('data.status', 'pending');

    Queue::assertPushedOn('ai', GenerateRecommendation::class);
});

it('refuses to generate recommendations for an empty segment', function () {
    Queue::fake();
    customerVisitedDaysAgo([10, 40]);
    app(AnalysisService::class)->run(CarbonImmutable::today());

    $this->postJson('/api/segments/cant_lose_them/recommendations')->assertUnprocessable();
    Queue::assertNothingPushed();
});

it('seeds a full demo: snapshots, backtest, recommendations and AI usage', function () {
    config(['retention.demo_customers' => 300, 'retention.ai.fake_malformed_first_answer' => true]);

    $this->seed(DemoSeeder::class);

    expect(AnalysisRun::where('kind', 'live')->count())->toBe(2)
        ->and(Recommendation::where('status', 'ready')->count())->toBeGreaterThanOrEqual(2);

    $this->getJson('/api/analysis/migration')->assertOk()->assertJsonStructure(['data' => ['matrix' => ['champions']]]);
    $this->getJson('/api/overview')->assertOk()->assertJsonStructure(['data' => ['backtest' => ['auc', 'baseline_recency_auc', 'deciles']]]);
    $this->getJson('/api/ai/usage')->assertOk()->assertJsonPath('data.by_purpose.0.purpose', 'recommendations');
    $this->getJson('/api/recommendations')->assertOk()->assertJsonPath('data.0.status', 'ready');
});
