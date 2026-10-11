<?php

namespace App\Http\Controllers\Api;

use App\Enums\Segment;
use App\Http\Controllers\Controller;
use App\Models\AnalysisRun;
use App\Models\CustomerScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SegmentController extends Controller
{
    public function index(): JsonResponse
    {
        $run = AnalysisRun::latestLive();
        $summary = $run?->summary['segments'] ?? [];

        return response()->json(['data' => collect(Segment::cases())->map(fn (Segment $s) => [
            'key' => $s->value,
            'label' => $s->label(),
            'goal' => $s->goal(),
            'retention_priority' => $s->isRetentionPriority(),
            ...($summary[$s->value] ?? []),
        ]), 'run_id' => $run?->id]);
    }

    public function customers(Request $request, Segment $segment): JsonResponse
    {
        $data = $request->validate([
            'sort' => ['nullable', Rule::in(['churn_risk', 'revenue_at_risk', 'recency_days', 'monetary'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $run = AnalysisRun::latestLive();
        abort_unless($run, 404, 'No analysis has been run yet.');

        $page = CustomerScore::with('customer:id,name,synthetic_profile')
            ->where('analysis_run_id', $run->id)
            ->where('segment', $segment)
            ->orderByDesc($data['sort'] ?? 'churn_risk')
            ->orderBy('customer_id')
            ->paginate($data['per_page'] ?? 25);

        return response()->json([
            'data' => $page->getCollection()->map(fn (CustomerScore $s) => [
                'customer_id' => $s->customer_id,
                'name' => $s->customer->name,
                'synthetic_profile' => $s->customer->synthetic_profile,
                'rfm' => "{$s->r}{$s->f}{$s->m}",
                'recency_days' => $s->recency_days,
                'frequency' => $s->frequency,
                'monetary' => $s->monetary,
                'churn_risk' => $s->churn_risk,
                'top_factor' => $s->risk_factors[0]['label'] ?? null,
                'next_visit_due' => $s->next_visit_due->toDateString(),
                'revenue_at_risk' => $s->revenue_at_risk,
            ]),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }
}
