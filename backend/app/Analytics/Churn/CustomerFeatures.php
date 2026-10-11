<?php

namespace App\Analytics\Churn;

use Carbon\CarbonImmutable;

/** Everything the scoring needs about one customer, computed as of a given date. */
final readonly class CustomerFeatures
{
    public function __construct(
        public int $customerId,
        public CarbonImmutable $lastVisit,
        public int $daysSinceLastVisit,
        public int $totalVisits,
        public int $visitsInPeriod,
        public float $spendInPeriod,
        public int $visitsLast180,
        public int $visitsPrior180,
        public int $expectedIntervalDays,
    ) {}
}
