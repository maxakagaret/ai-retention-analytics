<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerScore;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['visits.services']);

        $history = CustomerScore::query()
            ->join('analysis_runs', 'analysis_runs.id', '=', 'customer_scores.analysis_run_id')
            ->where('analysis_runs.kind', 'live')
            ->where('customer_scores.customer_id', $customer->id)
            ->orderBy('analysis_runs.as_of')
            ->get(['customer_scores.*', 'analysis_runs.as_of']);

        $latest = $history->last();

        return response()->json(['data' => [
            'id' => $customer->id,
            'name' => $customer->name,
            'joined_on' => $customer->joined_on->toDateString(),
            'synthetic_profile' => $customer->synthetic_profile,
            'score' => $latest ? [
                'segment' => $latest->segment->value,
                'segment_label' => $latest->segment->label(),
                'rfm' => ['r' => $latest->r, 'f' => $latest->f, 'm' => $latest->m],
                'recency_days' => $latest->recency_days,
                'frequency' => $latest->frequency,
                'monetary' => $latest->monetary,
                'churn_risk' => $latest->churn_risk,
                'risk_factors' => $latest->risk_factors,
                'expected_interval_days' => $latest->expected_interval_days,
                'next_visit_due' => $latest->next_visit_due->toDateString(),
                'revenue_at_risk' => $latest->revenue_at_risk,
            ] : null,
            'history' => $history->map(fn ($s) => [
                'as_of' => substr((string) $s->as_of, 0, 10),
                'segment' => $s->segment->value,
                'churn_risk' => $s->churn_risk,
            ]),
            'visits' => $customer->visits->sortByDesc('visited_on')->take(30)->values()->map(fn ($v) => [
                'date' => $v->visited_on->toDateString(),
                'total' => $v->total,
                'services' => $v->services->pluck('name'),
            ]),
        ]]);
    }
}
