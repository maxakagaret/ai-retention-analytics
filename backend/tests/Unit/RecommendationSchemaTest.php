<?php

use App\Ai\Recommendations\RecommendationSchema;

$item = fn (array $over = []) => array_merge([
    'title' => 'Win-back call', 'rationale' => 'Overdue by 2x', 'channel' => 'call',
    'timing' => 'This week', 'offer' => 'none', 'target_share' => 0.3, 'expected_impact' => 'high',
], $over);

it('accepts a valid answer, also inside a markdown fence', function () use ($item) {
    $json = json_encode(['summary' => 'ok', 'items' => [$item(), $item(['channel' => 'sms'])]]);

    expect((new RecommendationSchema)->parse($json)[1])->toBe([])
        ->and((new RecommendationSchema)->parse("```json\n{$json}\n```")[0]['summary'])->toBe('ok');
});

it('rejects broken JSON with a reason', function () {
    [$data, $errors] = (new RecommendationSchema)->parse('{"summary": "cut off');

    expect($data)->toBeNull()->and($errors[0])->toStartWith('Response is not valid JSON');
});

it('lists every contract violation so the model can fix them', function () use ($item) {
    $json = json_encode(['summary' => '', 'items' => [
        $item(['channel' => 'carrier pigeon', 'target_share' => 1.7]),
        $item(['expected_impact' => 'huge', 'title' => '']),
    ]]);

    [, $errors] = (new RecommendationSchema)->parse($json);

    expect($errors)->toContain(
        'summary must be a non-empty string',
        'items[0].channel must be one of sms, email, messenger, call',
        'items[0].target_share must be a number between 0 and 1',
        'items[1].expected_impact must be one of low, medium, high',
        'items[1].title must be a non-empty string',
    );
});

it('requires two to four items', function () use ($item) {
    [, $errors] = (new RecommendationSchema)->parse(json_encode(['summary' => 'x', 'items' => [$item()]]));

    expect($errors)->toContain('items must be an array of 2 to 4 objects');
});
