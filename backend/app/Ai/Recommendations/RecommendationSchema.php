<?php

namespace App\Ai\Recommendations;

/**
 * Validates the model's answer. LLM output is untrusted input: it is parsed,
 * checked field by field, and anything off-contract is sent back to the model
 * as a correction instead of being shown to users.
 */
class RecommendationSchema
{
    private const CHANNELS = ['sms', 'email', 'messenger', 'call'];

    private const IMPACT = ['low', 'medium', 'high'];

    /** @return array{0: ?array, 1: list<string>} parsed payload (or null) and errors */
    public function parse(string $text): array
    {
        $text = trim($text);
        // Tolerate a ```json fence — a common, harmless deviation.
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $m)) {
            $text = $m[1];
        }

        $data = json_decode($text, true);
        if (! is_array($data)) {
            return [null, ['Response is not valid JSON: '.json_last_error_msg()]];
        }

        $errors = [];
        if (! is_string($data['summary'] ?? null) || trim($data['summary']) === '') {
            $errors[] = 'summary must be a non-empty string';
        }

        $items = $data['items'] ?? null;
        if (! is_array($items) || count($items) < 2 || count($items) > 4) {
            $errors[] = 'items must be an array of 2 to 4 objects';
            $items = [];
        }

        foreach (array_values($items) as $i => $item) {
            foreach (['title', 'rationale', 'timing', 'offer'] as $field) {
                if (! is_string($item[$field] ?? null) || trim($item[$field]) === '') {
                    $errors[] = "items[{$i}].{$field} must be a non-empty string";
                }
            }
            if (! in_array($item['channel'] ?? null, self::CHANNELS, true)) {
                $errors[] = "items[{$i}].channel must be one of ".implode(', ', self::CHANNELS);
            }
            if (! in_array($item['expected_impact'] ?? null, self::IMPACT, true)) {
                $errors[] = "items[{$i}].expected_impact must be one of ".implode(', ', self::IMPACT);
            }
            $share = $item['target_share'] ?? null;
            if (! is_numeric($share) || $share < 0 || $share > 1) {
                $errors[] = "items[{$i}].target_share must be a number between 0 and 1";
            }
        }

        return $errors ? [null, $errors] : [$data, []];
    }
}
