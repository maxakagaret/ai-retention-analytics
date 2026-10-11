<?php

namespace App\Ai\Recommendations;

use App\Ai\BudgetExceededException;
use App\Ai\Contracts\LlmClient;
use App\Ai\LlmException;
use App\Ai\LlmRequest;
use App\Ai\UsageMeter;
use App\Enums\Segment;
use App\Models\AnalysisRun;
use App\Models\Recommendation;
use Illuminate\Support\Facades\Log;

/**
 * Segment → aggregates → prompt → model → validated recommendations.
 *
 *  - cache: the prompt hash is stored; regenerating with an identical prompt
 *    returns the stored answer without paying for another call;
 *  - budget: checked before every call;
 *  - self-repair: an invalid answer is sent back with the exact validation
 *    errors, up to `schema_retries` times;
 *  - accounting: every attempt (including failed ones) is recorded with tokens,
 *    cost and latency.
 */
class RecommendationService
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly UsageMeter $usage,
        private readonly SegmentContextBuilder $contexts,
        private readonly PromptBuilder $prompts,
        private readonly RecommendationSchema $schema,
    ) {}

    public function generate(AnalysisRun $run, Segment $segment, bool $force = false): Recommendation
    {
        $context = $this->contexts->build($run, $segment);
        $model = (string) config('retention.ai.model');
        $request = new LlmRequest($model, $this->prompts->system(), [
            ['role' => 'user', 'content' => $this->prompts->user($context)],
        ], (int) config('retention.ai.max_output_tokens'));
        $hash = hash('sha256', $model."\n".$request->system."\n".$request->messages[0]['content']);

        $recommendation = Recommendation::firstOrNew(['analysis_run_id' => $run->id, 'segment' => $segment]);

        if (! $force && $recommendation->exists && $recommendation->status === 'ready' && $recommendation->prompt_hash === $hash) {
            return $recommendation; // cache hit: same data, same prompt
        }

        $recommendation->fill(['status' => 'pending', 'context' => $context, 'prompt_hash' => $hash, 'model' => $model, 'error' => null])->save();

        try {
            $payload = $this->askWithRepair($request);
            $recommendation->update(['status' => 'ready', 'items' => $payload]);
        } catch (BudgetExceededException|LlmException $e) {
            $recommendation->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $recommendation->refresh();
    }

    /** @throws BudgetExceededException|LlmException */
    private function askWithRepair(LlmRequest $request): array
    {
        $attempts = 1 + (int) config('retention.ai.schema_retries');

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $this->usage->assertWithinBudget();

            $started = hrtime(true);
            try {
                $response = $this->llm->complete($request);
            } catch (LlmException $e) {
                $this->usage->record('recommendations', $request->model, 0, 0, $this->ms($started), false, $e->getMessage());
                throw $e;
            }

            [$payload, $errors] = $this->schema->parse($response->text);

            $this->usage->record('recommendations', $response->model, $response->inputTokens, $response->outputTokens,
                $this->ms($started), $payload !== null, $errors ? implode('; ', $errors) : null);

            if ($payload !== null) {
                return $payload;
            }

            Log::info('LLM answer rejected by schema, asking for a correction', ['attempt' => $attempt, 'errors' => $errors]);

            $request = $request
                ->withMessage('assistant', $response->text)
                ->withMessage('user', "Your answer was rejected:\n- ".implode("\n- ", $errors)."\nReturn the corrected JSON only.");
        }

        throw new LlmException('The model did not return a valid answer after '.$attempts.' attempts.');
    }

    private function ms(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
