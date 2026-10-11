<?php

namespace App\Ai;

use App\Models\LlmCall;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cost accounting for every model call (successful or not — failed calls are
 * billed too) and a hard monthly budget checked *before* calling the model.
 */
class UsageMeter
{
    public function cost(string $model, int $inputTokens, int $outputTokens): float
    {
        $price = config("retention.ai.pricing.{$model}", ['input' => 0, 'output' => 0]);

        return round(($inputTokens * $price['input'] + $outputTokens * $price['output']) / 1_000_000, 6);
    }

    public function record(string $purpose, string $model, int $inputTokens, int $outputTokens, int $latencyMs, bool $success, ?string $error = null): void
    {
        LlmCall::create([
            'purpose' => $purpose,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $this->cost($model, $inputTokens, $outputTokens),
            'latency_ms' => $latencyMs,
            'success' => $success,
            'error' => $error ? mb_substr($error, 0, 250) : null,
            'created_at' => CarbonImmutable::now(),
        ]);
    }

    public function spentThisMonth(): float
    {
        return (float) LlmCall::where('created_at', '>=', CarbonImmutable::now()->startOfMonth())->sum('cost_usd');
    }

    /** @throws BudgetExceededException */
    public function assertWithinBudget(): void
    {
        $budget = (float) config('retention.ai.monthly_budget_usd');
        $spent = $this->spentThisMonth();

        if ($spent >= $budget) {
            throw new BudgetExceededException($spent, $budget);
        }
    }

    public function stats(CarbonImmutable $since): array
    {
        $rows = LlmCall::where('created_at', '>=', $since)
            ->select('purpose', 'model',
                DB::raw('count(*) as calls'),
                DB::raw('sum(case when success then 0 else 1 end) as failures'),
                DB::raw('sum(input_tokens) as input_tokens'),
                DB::raw('sum(output_tokens) as output_tokens'),
                DB::raw('sum(cost_usd) as cost_usd'),
                DB::raw('avg(latency_ms) as avg_latency_ms'))
            ->groupBy('purpose', 'model')
            ->get()
            ->map(fn ($r) => [
                'purpose' => $r->purpose,
                'model' => $r->model,
                'calls' => (int) $r->calls,
                'failures' => (int) $r->failures,
                'input_tokens' => (int) $r->input_tokens,
                'output_tokens' => (int) $r->output_tokens,
                'cost_usd' => round((float) $r->cost_usd, 4),
                'avg_latency_ms' => (int) round((float) $r->avg_latency_ms),
            ]);

        return [
            'since' => $since->toIso8601String(),
            'by_purpose' => $rows,
            'month_spent_usd' => round($this->spentThisMonth(), 4),
            'month_budget_usd' => (float) config('retention.ai.monthly_budget_usd'),
        ];
    }
}
