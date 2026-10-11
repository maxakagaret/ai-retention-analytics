<?php

use App\Analytics\Churn\FeatureExtractor;
use Carbon\CarbonImmutable;

it('derives the personal rhythm from the median gap between visits', function () {
    $customer = customerVisitedDaysAgo([200, 170, 140, 110, 80, 10]);

    $f = app(FeatureExtractor::class)->extract(CarbonImmutable::today())[$customer->id];

    expect($f->expectedIntervalDays)->toBe(30)
        ->and($f->daysSinceLastVisit)->toBe(10)
        ->and($f->totalVisits)->toBe(6);
});

it('falls back to the typical interval of the favourite service for few visits', function () {
    $customer = customerVisitedDaysAgo([40, 20], service: service('Coloring', 60));

    expect(app(FeatureExtractor::class)->extract(CarbonImmutable::today())[$customer->id]->expectedIntervalDays)->toBe(60);
});

it('splits frequency and spend into the analysis window', function () {
    // RFM period is 365 days: the visit 400 days ago counts for recency history but not for F/M.
    $customer = customerVisitedDaysAgo([400, 100, 50], total: 80);

    $f = app(FeatureExtractor::class)->extract(CarbonImmutable::today())[$customer->id];

    expect($f->visitsInPeriod)->toBe(2)
        ->and($f->spendInPeriod)->toBe(160.0)
        ->and($f->visitsLast180)->toBe(2)
        ->and($f->visitsPrior180)->toBe(0);
});

it('ignores visits after the as-of date (no leakage into backtests)', function () {
    $customer = customerVisitedDaysAgo([150, 120, 90, 5]);

    $f = app(FeatureExtractor::class)->extract(CarbonImmutable::today()->subDays(60))[$customer->id];

    expect($f->totalVisits)->toBe(3)
        ->and($f->daysSinceLastVisit)->toBe(30);
});

it('drops customers inactive for longer than the active window', function () {
    $archived = customerVisitedDaysAgo([800, 700]);
    $active = customerVisitedDaysAgo([30]);

    $features = app(FeatureExtractor::class)->extract(CarbonImmutable::today());

    expect($features->has($archived->id))->toBeFalse()
        ->and($features->has($active->id))->toBeTrue();
});
