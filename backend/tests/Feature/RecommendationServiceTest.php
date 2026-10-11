<?php

use App\Ai\Contracts\LlmClient;
use App\Ai\LlmRequest;
use App\Ai\LlmResponse;
use App\Ai\Recommendations\RecommendationService;
use App\Analytics\AnalysisService;
use App\Enums\Segment;
use App\Models\Customer;
use App\Models\LlmCall;
use Carbon\CarbonImmutable;

beforeEach(function () {
    foreach (range(1, 4) as $i) {
        customerVisitedDaysAgo(regularVisits(330, 120, 20), total: 70);
    }
    $this->run = app(AnalysisService::class)->run(CarbonImmutable::today());
    $this->segment = $this->run->scores()->value('segment');
});

/** Records every request the service sends, delegating to the real fake model. */
class SpyLlm implements LlmClient
{
    public array $requests = [];

    public function complete(LlmRequest $request): LlmResponse
    {
        $this->requests[] = $request;

        return app(\App\Ai\Fakes\FakeLlmClient::class)->complete($request);
    }
}

it('produces validated, number-grounded recommendations', function () {
    $rec = app(RecommendationService::class)->generate($this->run, $this->segment);

    expect($rec->status)->toBe('ready')
        ->and(count($rec->items['items']))->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(4)
        ->and($rec->items['summary'])->toContain('4 customers')
        ->and($rec->context['customers'])->toBe(4)
        ->and(LlmCall::sole()->success)->toBeTrue()
        ->and(LlmCall::sole()->cost_usd)->toBeGreaterThan(0);
});

it('repairs an invalid answer by sending the validation errors back', function () {
    config(['retention.ai.fake_malformed_first_answer' => true]);
    $this->app->instance(LlmClient::class, $spy = new SpyLlm);

    $rec = app(RecommendationService::class)->generate($this->run, $this->segment);

    expect($rec->status)->toBe('ready')
        ->and($spy->requests)->toHaveCount(2)
        ->and(end($spy->requests)->messages[2]['content'])->toContain('Your answer was rejected')->toContain('not valid JSON')
        ->and(LlmCall::pluck('success')->all())->toBe([false, true]);
});

it('gives up after the retry budget and marks the recommendation failed', function () {
    $this->app->instance(LlmClient::class, new class implements LlmClient {
        public function complete(LlmRequest $request): LlmResponse
        {
            return new LlmResponse('I think you should call them.', $request->model, 100, 10);
        }
    });

    $rec = app(RecommendationService::class)->generate($this->run, $this->segment);

    expect($rec->status)->toBe('failed')
        ->and($rec->error)->toContain('after 3 attempts')
        ->and(LlmCall::count())->toBe(3);
});

it('serves an identical prompt from cache without paying again', function () {
    $service = app(RecommendationService::class);
    $service->generate($this->run, $this->segment);
    $service->generate($this->run, $this->segment);

    expect(LlmCall::count())->toBe(1);

    $service->generate($this->run, $this->segment, force: true);
    expect(LlmCall::count())->toBe(2);
});

it('refuses to call the model once the monthly budget is spent', function () {
    LlmCall::create(['purpose' => 'x', 'model' => 'fake-analyst-1', 'input_tokens' => 0, 'output_tokens' => 0,
        'cost_usd' => 25, 'latency_ms' => 1, 'success' => true, 'created_at' => now()]);

    $rec = app(RecommendationService::class)->generate($this->run, $this->segment);

    expect($rec->status)->toBe('failed')
        ->and($rec->error)->toContain('budget exhausted')
        ->and(LlmCall::count())->toBe(1);
});

it('never sends personal data to the model', function () {
    $this->app->instance(LlmClient::class, $spy = new SpyLlm);

    app(RecommendationService::class)->generate($this->run, $this->segment);

    $prompt = json_encode($spy->requests);
    foreach (Customer::all() as $customer) {
        expect($prompt)->not->toContain($customer->name)->not->toContain($customer->email);
    }
    expect($prompt)->not->toContain('customer_id');
});
