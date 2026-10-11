<?php

namespace App\Analytics\Churn;

/**
 * Explainable churn-risk score (0–100) built from three signals:
 *
 *  overdue   — days since the last visit relative to the customer's *own* rhythm
 *              (median gap between visits), squashed by a logistic curve: being
 *              a bit late is normal, being 3× late is not;
 *  trend     — drop in visits over the last 180 days vs the 180 days before;
 *  one_timer — a single visit carries little evidence of a habit.
 *
 * Every score comes with its factors and their contribution, so a manager can see
 * *why* a customer is flagged. The weights are configurable and the whole model is
 * checked against real outcomes by the Backtester (ROC AUC, calibration by decile).
 */
class ChurnRiskModel
{
    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self(config('retention.churn'));
    }

    /**
     * @return array{risk: int, factors: list<array{key: string, label: string, impact: int}>}
     */
    public function score(CustomerFeatures $f): array
    {
        $weights = $this->config['weights'];
        $ratio = $f->expectedIntervalDays > 0 ? $f->daysSinceLastVisit / $f->expectedIntervalDays : 0.0;

        $overdue = 1 / (1 + exp(-$this->config['logistic_steepness'] * ($ratio - $this->config['logistic_midpoint'])));
        $trend = $f->visitsPrior180 > 0 ? max(0.0, ($f->visitsPrior180 - $f->visitsLast180) / $f->visitsPrior180) : 0.0;
        $oneTimer = $f->totalVisits <= 1 ? 1.0 : 0.0;

        $parts = [
            'overdue' => $weights['overdue'] * $overdue,
            'trend' => $weights['trend'] * $trend,
            'one_timer' => $weights['one_timer'] * $oneTimer,
        ];

        $risk = (int) round(min(1.0, array_sum($parts)) * 100);

        $labels = [
            'overdue' => sprintf(
                '%d days since the last visit; usual rhythm is %d days (%.1f×)',
                $f->daysSinceLastVisit, $f->expectedIntervalDays, $ratio,
            ),
            'trend' => sprintf('Visits dropped from %d to %d (last 180 days vs the 180 before)', $f->visitsPrior180, $f->visitsLast180),
            'one_timer' => 'Only one visit so far — no habit yet',
        ];

        $factors = collect($parts)
            ->map(fn (float $value, string $key) => ['key' => $key, 'label' => $labels[$key], 'impact' => (int) round($value * 100)])
            ->filter(fn (array $factor) => $factor['impact'] >= 1)
            ->sortByDesc('impact')
            ->values()
            ->all();

        return ['risk' => $risk, 'factors' => $factors];
    }
}
