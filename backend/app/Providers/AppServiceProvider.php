<?php

namespace App\Providers;

use App\Ai\Contracts\LlmClient;
use App\Ai\Fakes\FakeLlmClient;
use App\Analytics\Churn\ChurnRiskModel;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The only LLM binding. A real provider client implements the same interface.
        $this->app->bind(LlmClient::class, FakeLlmClient::class);

        $this->app->bind(ChurnRiskModel::class, fn () => ChurnRiskModel::fromConfig());
    }
}
