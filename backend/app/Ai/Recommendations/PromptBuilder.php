<?php

namespace App\Ai\Recommendations;

/**
 * Grounded prompt: the model gets the numbers as a JSON block inside <context>
 * tags, a strict output contract, and is told to use only those numbers.
 */
class PromptBuilder
{
    public function system(): string
    {
        return <<<'TXT'
You are a retention analyst for a service business that works by appointment.
You design practical retention campaigns for one customer segment at a time.
Rules:
- Base every statement on the numbers in <context>; do not invent figures.
- Prefer actions the business can run this week with its own channels.
- Do not recommend discounts for segments that are already highly loyal.
- Answer with JSON only, no prose and no markdown fences.
TXT;
    }

    public function user(array $context): string
    {
        $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<TXT
<context>
{$json}
</context>

Return a JSON object of this shape:
{
  "summary": "one sentence diagnosis of the segment",
  "items": [
    {
      "title": "short campaign name",
      "rationale": "why, citing numbers from the context",
      "channel": "sms | email | messenger | call",
      "timing": "when to send relative to the customer's rhythm",
      "offer": "what to offer, or 'none'",
      "target_share": 0.0-1.0 share of the segment to target,
      "expected_impact": "low | medium | high"
    }
  ]
}
Give 2 to 4 items, ordered by expected impact.
TXT;
    }
}
