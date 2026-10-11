<?php

namespace App\Ai;

use RuntimeException;

class BudgetExceededException extends RuntimeException
{
    public function __construct(public readonly float $spent, public readonly float $budget)
    {
        parent::__construct(sprintf('Monthly AI budget exhausted: $%.2f of $%.2f spent.', $spent, $budget));
    }
}
