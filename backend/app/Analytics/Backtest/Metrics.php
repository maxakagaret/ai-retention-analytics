<?php

namespace App\Analytics\Backtest;

/** Classification metrics, dependency-free. */
final class Metrics
{
    /**
     * ROC AUC via the Mann–Whitney U statistic with average ranks for ties:
     * the probability that a random positive gets a higher score than a random negative.
     *
     * @param  list<float|int>  $scores
     * @param  list<bool>  $labels  true = positive (churned)
     */
    public static function rocAuc(array $scores, array $labels): ?float
    {
        $positives = count(array_filter($labels));
        $negatives = count($labels) - $positives;
        if ($positives === 0 || $negatives === 0) {
            return null;
        }

        $order = array_keys($scores);
        usort($order, fn ($a, $b) => $scores[$a] <=> $scores[$b]);

        $ranks = [];
        for ($i = 0, $n = count($order); $i < $n;) {
            $j = $i;
            while ($j + 1 < $n && $scores[$order[$j + 1]] == $scores[$order[$i]]) {
                $j++;
            }
            $avg = ($i + $j) / 2 + 1;
            for ($k = $i; $k <= $j; $k++) {
                $ranks[$order[$k]] = $avg;
            }
            $i = $j + 1;
        }

        $rankSum = 0.0;
        foreach ($labels as $index => $positive) {
            if ($positive) {
                $rankSum += $ranks[$index];
            }
        }

        return round(($rankSum - $positives * ($positives + 1) / 2) / ($positives * $negatives), 4);
    }

    /**
     * Actual positive rate per score decile (0–9, by score value 0–100).
     *
     * @return list<array{decile: string, customers: int, actual_rate: ?float}>
     */
    public static function deciles(array $scores, array $labels): array
    {
        $buckets = array_fill(0, 10, ['n' => 0, 'pos' => 0]);
        foreach ($scores as $i => $score) {
            $b = min(9, intdiv((int) $score, 10));
            $buckets[$b]['n']++;
            $buckets[$b]['pos'] += $labels[$i] ? 1 : 0;
        }

        return array_map(fn (int $b) => [
            'decile' => ($b * 10).'–'.($b * 10 + 9),
            'customers' => $buckets[$b]['n'],
            'actual_rate' => $buckets[$b]['n'] ? round($buckets[$b]['pos'] / $buckets[$b]['n'], 4) : null,
        ], range(0, 9));
    }

    public static function precisionAtK(array $scores, array $labels, int $k): ?float
    {
        if ($scores === []) {
            return null;
        }
        arsort($scores);
        $top = array_slice(array_keys($scores), 0, $k, true);

        return round(count(array_filter($top, fn ($i) => $labels[$i])) / count($top), 4);
    }
}
