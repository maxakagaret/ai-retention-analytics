<?php

namespace App\Http\Controllers\Api;

use App\Ai\UsageMeter;
use App\Enums\Segment;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateRecommendation;
use App\Models\AnalysisRun;
use App\Models\Recommendation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function index(): JsonResponse
    {
        $run = AnalysisRun::latestLive();

        return response()->json([
            'data' => $run ? $run->recommendations()->latest('id')->get()->map(fn (Recommendation $r) => $this->present($r)) : [],
            'run_id' => $run?->id,
        ]);
    }

    /** Queues generation; the UI polls GET /recommendations/{id}. */
    public function generate(Request $request, Segment $segment): JsonResponse
    {
        $run = AnalysisRun::latestLive();
        abort_unless($run, 422, 'Run an analysis first.');
        abort_if(($run->summary['segments'][$segment->value]['customers'] ?? 0) === 0, 422, 'This segment has no customers in the latest snapshot.');

        $recommendation = Recommendation::firstOrCreate(
            ['analysis_run_id' => $run->id, 'segment' => $segment],
            ['status' => 'pending'],
        );

        GenerateRecommendation::dispatch($run->id, $segment, $request->boolean('force'))->onQueue(config('retention.queue'));

        return response()->json(['data' => $this->present($recommendation->refresh())], 202);
    }

    public function show(Recommendation $recommendation): JsonResponse
    {
        return response()->json(['data' => $this->present($recommendation)]);
    }

    public function usage(UsageMeter $usage): JsonResponse
    {
        return response()->json(['data' => $usage->stats(CarbonImmutable::now()->subDays(30))]);
    }

    private function present(Recommendation $r): array
    {
        return [
            'id' => $r->id,
            'segment' => $r->segment->value,
            'segment_label' => $r->segment->label(),
            'status' => $r->status,
            'summary' => $r->items['summary'] ?? null,
            'items' => $r->items['items'] ?? [],
            'context' => $r->context,
            'model' => $r->model,
            'error' => $r->error,
            'updated_at' => $r->updated_at?->toIso8601String(),
        ];
    }
}
