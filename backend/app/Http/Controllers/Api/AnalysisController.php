<?php

namespace App\Http\Controllers\Api;

use App\Analytics\AnalysisService;
use App\Analytics\Backtest\Backtester;
use App\Analytics\SegmentMigration;
use App\Http\Controllers\Controller;
use App\Models\AnalysisRun;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalysisController extends Controller
{
    /** Dashboard: latest live snapshot + latest model-quality check. */
    public function overview(): JsonResponse
    {
        $run = AnalysisRun::latestLive();
        $backtest = AnalysisRun::where('kind', 'backtest')->latest('id')->first();

        return response()->json(['data' => [
            'run' => $run ? $this->runData($run) : null,
            'backtest' => $backtest?->summary['backtest'] ?? null,
        ]]);
    }

    public function runs(): JsonResponse
    {
        return response()->json(['data' => AnalysisRun::latest('id')->limit(50)->get()->map(fn ($r) => [
            'id' => $r->id,
            'as_of' => $r->as_of->toDateString(),
            'kind' => $r->kind,
            'customers' => $r->summary['customers'] ?? null,
            'revenue_at_risk' => $r->summary['revenue_at_risk'] ?? null,
            'created_at' => $r->created_at?->toIso8601String(),
        ])]);
    }

    public function run(Request $request, AnalysisService $analysis): JsonResponse
    {
        $data = $request->validate(['as_of' => ['nullable', 'date', 'before_or_equal:today']]);
        $asOf = isset($data['as_of']) ? CarbonImmutable::parse($data['as_of']) : CarbonImmutable::today();

        return response()->json(['data' => $this->runData($analysis->run($asOf))], 201);
    }

    public function migration(Request $request, SegmentMigration $migration): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'integer', 'exists:analysis_runs,id'],
            'to' => ['nullable', 'integer', 'exists:analysis_runs,id'],
        ]);

        $live = AnalysisRun::live()->latest('id')->limit(2)->get();
        $to = isset($data['to']) ? AnalysisRun::find($data['to']) : $live->first();
        $from = isset($data['from']) ? AnalysisRun::find($data['from']) : $live->skip(1)->first();

        if (! $from || ! $to) {
            return response()->json(['message' => 'Two analysis runs are needed to compare.'], 422);
        }

        return response()->json(['data' => [
            ...$migration->between($from, $to),
            'from_as_of' => $from->as_of->toDateString(),
            'to_as_of' => $to->as_of->toDateString(),
        ]]);
    }

    public function backtest(Request $request, Backtester $backtester): JsonResponse
    {
        $data = $request->validate(['horizon_days' => ['nullable', 'integer', 'min:30', 'max:365']]);
        $run = $backtester->run(horizonDays: $data['horizon_days'] ?? null);

        return response()->json(['data' => $run->summary['backtest']], 201);
    }

    private function runData(AnalysisRun $run): array
    {
        return [
            'id' => $run->id,
            'as_of' => $run->as_of->toDateString(),
            'summary' => $run->summary,
            'params' => $run->params,
        ];
    }
}
