<?php

namespace App\Ai\Fakes;

use App\Ai\Contracts\LlmClient;
use App\Ai\LlmException;
use App\Ai\LlmRequest;
use App\Ai\LlmResponse;

/**
 * Deterministic stand-in for an LLM. It reads the <context> block like a real
 * model would and writes recommendations from rule-based templates, citing the
 * actual numbers. Token counts are estimated (~4 characters per token) so cost
 * accounting works end to end.
 *
 * With `fake_malformed_first_answer` on, the first answer to a fresh request is
 * truncated JSON — this exercises the schema-repair loop in the demo.
 */
class FakeLlmClient implements LlmClient
{
    public function complete(LlmRequest $request): LlmResponse
    {
        $prompt = $request->system."\n".implode("\n", array_column($request->messages, 'content'));

        if (! preg_match('/<context>\s*(\{.*\})\s*<\/context>/s', $prompt, $m) || ! is_array($ctx = json_decode($m[1], true))) {
            throw new LlmException('Fake model: no <context> block in the prompt.');
        }

        $answer = json_encode($this->recommend($ctx), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $isRetry = count($request->messages) > 1;
        if (config('retention.ai.fake_malformed_first_answer') && ! $isRetry) {
            $answer = mb_substr($answer, 0, (int) (mb_strlen($answer) * 0.6)); // cut off mid-object
        }

        return new LlmResponse(
            text: $answer,
            model: $request->model,
            inputTokens: (int) ceil(mb_strlen($prompt) / 4),
            outputTokens: (int) ceil(mb_strlen($answer) / 4),
        );
    }

    private function recommend(array $c): array
    {
        $rhythm = $c['avg_usual_interval_days'];
        $service = $c['top_services'][0]['service'] ?? 'their usual service';
        $money = fn (float $v) => '$'.number_format($v, 0);

        $items = match ($c['segment']) {
            'champions', 'loyal' => [
                $this->item('Referral invitation', "Avg {$c['avg_visits_per_year']} visits/year and {$money($c['avg_annual_spend'])} annual spend: these customers already love the service — reward advocacy, not price.", 'messenger', 'Right after the next visit', 'Bring-a-friend perk for both', 0.6, 'high'),
                $this->item("Add-on to {$service}", "{$service} dominates their visits; a complementary service raises ticket size without discounts.", 'messenger', "At booking, {$rhythm} days after the last visit", 'Add-on at regular price, first one complimentary', 0.4, 'medium'),
            ],
            'at_risk', 'cant_lose_them' => [
                $this->item('Personal win-back call', "{$c['customers']} customers, avg {$c['avg_days_since_last_visit']} days since the last visit vs a usual rhythm of {$rhythm} days; {$money($c['revenue_at_risk'])} of annual revenue at risk.", 'call', 'This week, highest spenders first', "Priority slot for {$service}", 0.3, 'high'),
                $this->item('We-miss-you message', "Avg churn risk {$c['avg_churn_risk']}/100 — a time-limited reason to return works better than a generic newsletter.", 'sms', 'Now, then a reminder in 7 days', '15% off the next visit, valid 14 days', 0.7, 'medium'),
                $this->item('Feedback check-in', 'Some of them left for a reason; asking before offering keeps the tone honest and surfaces service issues.', 'email', 'Together with the offer', 'none', 0.5, 'low'),
            ],
            'new_customers', 'promising', 'potential_loyalist' => [
                $this->item('Second-visit nudge', "First impressions fade: book the next {$service} before day {$rhythm}, the typical repeat interval.", 'messenger', 'Day '.max(7, $rhythm - 7).' after the last visit', 'Booking link with a preferred-time pick', 0.8, 'high'),
                $this->item('Welcome series', "Only {$c['avg_visits_per_year']} visits/year on average — explain the full menu to create a habit.", 'email', 'Weeks 1 and 3 after the first visit', 'none', 0.6, 'medium'),
            ],
            default => [
                $this->item('Reactivation message', "{$c['customers']} customers, avg {$c['avg_days_since_last_visit']} days without a visit; low-cost channel first.", 'sms', 'Now', '10% off, valid 21 days', 0.6, 'medium'),
                $this->item('Last-chance email', "Avg churn risk {$c['avg_churn_risk']}/100; stop spending on those who do not react.", 'email', '3 weeks after the reactivation message', 'Free add-on with the next booking', 0.4, 'low'),
            ],
        };

        return [
            'summary' => sprintf('%s: %d customers (%.0f%% of the base), %s at risk, typical rhythm %d days.',
                $c['segment_label'], $c['customers'], $c['share_of_base'] * 100, $money($c['revenue_at_risk']), $rhythm),
            'items' => $items,
        ];
    }

    private function item(string $title, string $rationale, string $channel, string $timing, string $offer, float $share, string $impact): array
    {
        return [
            'title' => $title, 'rationale' => $rationale, 'channel' => $channel, 'timing' => $timing,
            'offer' => $offer, 'target_share' => $share, 'expected_impact' => $impact,
        ];
    }
}
