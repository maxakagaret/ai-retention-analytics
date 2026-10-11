<?php

use App\Analytics\Churn\ChurnRiskModel;
use App\Analytics\Churn\CustomerFeatures;
use Carbon\CarbonImmutable;

function features(int $daysSince, int $interval, int $total = 10, int $last180 = 4, int $prior180 = 4): CustomerFeatures
{
    return new CustomerFeatures(1, CarbonImmutable::today()->subDays($daysSince), $daysSince, $total, $total, 500.0, $last180, $prior180, $interval);
}

beforeEach(fn () => $this->model = ChurnRiskModel::fromConfig());

it('keeps a customer on schedule at low risk', function () {
    expect($this->model->score(features(20, 30))['risk'])->toBeLessThan(15);
});

it('flags a customer far beyond their own rhythm', function () {
    expect($this->model->score(features(120, 30))['risk'])->toBeGreaterThan(60);
});

it('judges overdue relative to the personal rhythm, not absolute days', function () {
    // 60 days without a visit: alarming for a monthly customer, normal for a quarterly one.
    $monthly = $this->model->score(features(60, 30))['risk'];
    $quarterly = $this->model->score(features(60, 90))['risk'];

    expect($monthly)->toBeGreaterThan($quarterly + 20);
});

it('adds risk for a declining visit trend and for one-timers', function () {
    $steady = $this->model->score(features(30, 30))['risk'];
    $declining = $this->model->score(features(30, 30, last180: 1, prior180: 5))['risk'];
    $oneTimer = $this->model->score(features(30, 30, total: 1, last180: 1, prior180: 0))['risk'];

    expect($declining)->toBeGreaterThan($steady)
        ->and($oneTimer)->toBeGreaterThan($steady);
});

it('explains the score with factors ordered by impact', function () {
    $result = $this->model->score(features(150, 30, last180: 1, prior180: 4));

    expect(array_column($result['factors'], 'key'))->toBe(['overdue', 'trend'])
        ->and($result['factors'][0]['label'])->toContain('150 days since the last visit')->toContain('(5.0×)')
        ->and(array_sum(array_column($result['factors'], 'impact')))->toBeGreaterThanOrEqual($result['risk'] - 1);
});

it('never exceeds 100', function () {
    expect($this->model->score(features(2000, 7, total: 1, last180: 0, prior180: 9))['risk'])->toBeLessThanOrEqual(100);
});
