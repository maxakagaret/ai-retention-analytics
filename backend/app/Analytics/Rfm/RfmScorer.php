<?php

namespace App\Analytics\Rfm;

/**
 * RFM scores 1..5.
 *
 * Recency and Monetary use quintile thresholds computed from the current
 * customer base, so scores adapt to the business (a premium spa and a barber
 * have very different "high spend"). Frequency is a count with heavy ties at 1
 * and 2 — quantiles would smear one-time customers across scores — so it uses
 * fixed, explainable bins instead.
 */
class RfmScorer
{
    /** Visits in the period → F score. */
    public const FREQUENCY_BINS = [1 => 1, 2 => 2, 3 => 3, 5 => 4, 9 => 5];

    /**
     * Quintile cut points (20/40/60/80th percentile) using linear interpolation.
     *
     * @param  list<float|int>  $values
     * @return list<float> four ascending thresholds
     */
    public static function quintiles(array $values): array
    {
        if ($values === []) {
            return [0, 0, 0, 0];
        }

        sort($values);
        $n = count($values);

        return array_map(function (float $p) use ($values, $n) {
            $pos = ($n - 1) * $p;
            $lower = (int) floor($pos);
            $upper = (int) ceil($pos);

            return $values[$lower] + ($values[$upper] - $values[$lower]) * ($pos - $lower);
        }, [0.2, 0.4, 0.6, 0.8]);
    }

    /** Higher value = better score (Monetary). */
    public static function scoreAscending(float $value, array $thresholds): int
    {
        $score = 1;
        foreach ($thresholds as $cut) {
            if ($value > $cut) {
                $score++;
            }
        }

        return $score;
    }

    /** Lower value = better score (Recency in days). */
    public static function scoreDescending(float $value, array $thresholds): int
    {
        return 6 - self::scoreAscending($value, $thresholds);
    }

    public static function frequencyScore(int $visits): int
    {
        $score = 1;
        foreach (self::FREQUENCY_BINS as $minVisits => $binScore) {
            if ($visits >= $minVisits) {
                $score = $binScore;
            }
        }

        return $score;
    }
}
