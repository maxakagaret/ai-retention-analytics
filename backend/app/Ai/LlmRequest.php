<?php

namespace App\Ai;

final readonly class LlmRequest
{
    /** @param list<array{role: 'user'|'assistant', content: string}> $messages */
    public function __construct(
        public string $model,
        public string $system,
        public array $messages,
        public int $maxTokens,
        public float $temperature = 0.2,
    ) {}

    public function withMessage(string $role, string $content): self
    {
        return new self($this->model, $this->system, [...$this->messages, ['role' => $role, 'content' => $content]], $this->maxTokens, $this->temperature);
    }
}
