<?php

namespace App\Analytics;

use App\Enums\Segment;
use App\Models\AnalysisRun;
use Illuminate\Support\Facades\DB;

/**
 * How customers moved between segments from one snapshot to the next — the
 * "are our retention efforts working" view (e.g. how many at-risk customers
 * became loyal again, how many champions slipped).
 */
class SegmentMigration
{
    /** @return array{from_run: int, to_run: int, matrix: array<string, array<string, int>>, churned_out: array<string, int>} */
    public function between(AnalysisRun $from, AnalysisRun $to): array
    {
        $pairs = DB::table('customer_scores as a')
            ->join('customer_scores as b', function ($join) use ($to) {
                $join->on('a.customer_id', '=', 'b.customer_id')->where('b.analysis_run_id', '=', $to->id);
            })
            ->where('a.analysis_run_id', $from->id)
            ->select('a.segment as from_segment', 'b.segment as to_segment', DB::raw('count(*) as total'))
            ->groupBy('a.segment', 'b.segment')
            ->get();

        $keys = array_map(fn (Segment $s) => $s->value, Segment::cases());
        $matrix = array_fill_keys($keys, array_fill_keys($keys, 0));
        foreach ($pairs as $pair) {
            $matrix[$pair->from_segment][$pair->to_segment] = (int) $pair->total;
        }

        // Customers present in the earlier snapshot but no longer active in the later one.
        $churnedOut = DB::table('customer_scores as a')
            ->leftJoin('customer_scores as b', function ($join) use ($to) {
                $join->on('a.customer_id', '=', 'b.customer_id')->where('b.analysis_run_id', '=', $to->id);
            })
            ->where('a.analysis_run_id', $from->id)
            ->whereNull('b.customer_id')
            ->select('a.segment', DB::raw('count(*) as total'))
            ->groupBy('a.segment')
            ->pluck('total', 'segment')
            ->map(fn ($v) => (int) $v)
            ->all();

        return ['from_run' => $from->id, 'to_run' => $to->id, 'matrix' => $matrix, 'churned_out' => $churnedOut];
    }
}
