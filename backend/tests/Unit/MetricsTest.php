<?php

use App\Analytics\Backtest\Metrics;

it('gives AUC 1 for a perfect ranking and 0 for an inverted one', function () {
    expect(Metrics::rocAuc([10, 20, 80, 90], [false, false, true, true]))->toBe(1.0)
        ->and(Metrics::rocAuc([90, 80, 20, 10], [false, false, true, true]))->toBe(0.0);
});

it('treats ties as half-correct', function () {
    expect(Metrics::rocAuc([50, 50, 50, 50], [true, false, true, false]))->toBe(0.5);
});

it('matches a hand-computed AUC', function () {
    // Positives: 0.8, 0.4 · negatives: 0.6, 0.2 → pairs won: (0.8>0.6),(0.8>0.2),(0.4>0.2) = 3 of 4.
    expect(Metrics::rocAuc([0.8, 0.6, 0.4, 0.2], [true, false, true, false]))->toBe(0.75);
});

it('is undefined without both classes', function () {
    expect(Metrics::rocAuc([1, 2], [true, true]))->toBeNull();
});

it('reports the actual rate per score decile', function () {
    $deciles = Metrics::deciles([5, 7, 95, 99], [false, true, true, true]);

    expect($deciles[0])->toBe(['decile' => '0–9', 'customers' => 2, 'actual_rate' => 0.5])
        ->and($deciles[9])->toBe(['decile' => '90–99', 'customers' => 2, 'actual_rate' => 1.0])
        ->and($deciles[5]['actual_rate'])->toBeNull();
});

it('computes precision of the top-k list', function () {
    expect(Metrics::precisionAtK([90, 80, 70, 10], [true, false, true, false], 2))->toBe(0.5)
        ->and(Metrics::precisionAtK([90, 80], [true, true], 10))->toBe(1.0);
});
