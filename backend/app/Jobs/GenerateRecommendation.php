<?php

namespace App\Jobs;

use App\Ai\Recommendations\RecommendationService;
use App\Enums\Segment;
use App\Models\AnalysisRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * LLM calls take seconds, so they never run inside the HTTP request. Unique per
 * (run, segment): double-clicking "generate" does not pay twice.
 * One try only — the service has its own bounded repair loop.
 */
class GenerateRecommendation implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $runId,
        public readonly Segment $segment,
        public readonly bool $force = false,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->runId}:{$this->segment->value}";
    }

    public function handle(RecommendationService $service): void
    {
        $run = AnalysisRun::find($this->runId);

        if ($run) {
            $service->generate($run, $this->segment, $this->force);
        }
    }
}
