<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->date('joined_on');
            // Ground truth of the synthetic generator (loyal, fading, churned, ...).
            // Never used by the models — only to sanity-check them in the UI.
            $table->string('synthetic_profile', 24)->index();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 32);
            $table->decimal('price', 10, 2);
            // How often people usually repeat this service; fallback rhythm for new customers.
            $table->unsignedSmallInteger('typical_interval_days');
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('visited_on');
            $table->decimal('total', 10, 2);
            $table->index(['customer_id', 'visited_on']);
            $table->index('visited_on');
        });

        Schema::create('visit_services', function (Blueprint $table) {
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->primary(['visit_id', 'service_id']);
        });

        // One analysis = a snapshot of all scores as of a date (history, transitions, backtests).
        Schema::create('analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->date('as_of');
            $table->string('kind', 16)->default('live'); // live | backtest
            $table->json('params');
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_scores', function (Blueprint $table) {
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('recency_days');
            $table->unsignedInteger('frequency');
            $table->decimal('monetary', 12, 2);
            $table->unsignedTinyInteger('r');
            $table->unsignedTinyInteger('f');
            $table->unsignedTinyInteger('m');
            $table->string('segment', 24);
            $table->unsignedTinyInteger('churn_risk'); // 0..100
            $table->json('risk_factors');
            $table->unsignedSmallInteger('expected_interval_days');
            $table->date('next_visit_due');
            $table->decimal('revenue_at_risk', 12, 2);
            $table->primary(['analysis_run_id', 'customer_id']);
            $table->index(['analysis_run_id', 'segment']);
            $table->index(['analysis_run_id', 'churn_risk']);
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->string('segment', 24);
            $table->string('status', 16)->default('pending'); // pending | ready | failed
            $table->json('items')->nullable();
            $table->json('context')->nullable(); // exactly what the model saw (aggregates only)
            $table->string('prompt_hash', 64)->nullable()->index();
            $table->string('model', 64)->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
            $table->unique(['analysis_run_id', 'segment']);
        });

        // Every LLM call: cost accounting, latency, failures, cache hits.
        Schema::create('llm_calls', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 32);
            $table->string('model', 64);
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->decimal('cost_usd', 10, 6);
            $table->unsignedInteger('latency_ms');
            $table->boolean('success');
            $table->string('error')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        foreach (['llm_calls', 'recommendations', 'customer_scores', 'analysis_runs', 'visit_services', 'visits', 'services', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
