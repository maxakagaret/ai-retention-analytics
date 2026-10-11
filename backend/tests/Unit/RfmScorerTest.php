<?php

use App\Analytics\Rfm\RfmScorer;
use App\Enums\Segment;

it('computes quintile cut points with interpolation', function () {
    expect(RfmScorer::quintiles(range(1, 11)))->toBe([3.0, 5.0, 7.0, 9.0])
        ->and(RfmScorer::quintiles([]))->toBe([0, 0, 0, 0]);
});

it('scores monetary upwards and recency downwards', function () {
    $cuts = [10.0, 20.0, 30.0, 40.0];

    expect(RfmScorer::scoreAscending(5, $cuts))->toBe(1)
        ->and(RfmScorer::scoreAscending(45, $cuts))->toBe(5)
        ->and(RfmScorer::scoreAscending(20, $cuts))->toBe(2) // on the cut point: not above it
        ->and(RfmScorer::scoreDescending(5, $cuts))->toBe(5)  // visited 5 days ago → best recency
        ->and(RfmScorer::scoreDescending(45, $cuts))->toBe(1);
});

it('scores frequency with fixed, explainable bins', function (int $visits, int $score) {
    expect(RfmScorer::frequencyScore($visits))->toBe($score);
})->with([[0, 1], [1, 1], [2, 2], [3, 3], [4, 3], [5, 4], [8, 4], [9, 5], [30, 5]]);

it('maps R/F scores to segments in rule order', function (int $r, int $f, Segment $segment) {
    expect(Segment::classify($r, $f))->toBe($segment);
})->with([
    [5, 5, Segment::Champions],
    [3, 4, Segment::Loyal],
    [5, 1, Segment::NewCustomers],
    [4, 2, Segment::PotentialLoyalist],
    [3, 2, Segment::Promising],
    [3, 3, Segment::NeedAttention],
    [2, 3, Segment::AtRisk],
    [1, 5, Segment::CantLoseThem],
    [2, 1, Segment::Hibernating],
    [1, 1, Segment::Lost],
]);
