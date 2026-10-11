<?php

namespace App\Ai\Contracts;

use App\Ai\LlmException;
use App\Ai\LlmRequest;
use App\Ai\LlmResponse;

/**
 * Chat-completion style LLM provider. The demo binds FakeLlmClient; a real
 * implementation only needs to map LlmRequest to the vendor's messages API
 * and report token usage back in LlmResponse.
 */
interface LlmClient
{
    /** @throws LlmException on transport/provider errors */
    public function complete(LlmRequest $request): LlmResponse;
}
