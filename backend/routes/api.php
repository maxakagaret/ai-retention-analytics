<?php

use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\SegmentController;
use Illuminate\Support\Facades\Route;

Route::get('overview', [AnalysisController::class, 'overview']);
Route::get('analysis/runs', [AnalysisController::class, 'runs']);
Route::post('analysis/runs', [AnalysisController::class, 'run'])->middleware('throttle:10,1');
Route::get('analysis/migration', [AnalysisController::class, 'migration']);
Route::post('analysis/backtests', [AnalysisController::class, 'backtest'])->middleware('throttle:10,1');

Route::get('segments', [SegmentController::class, 'index']);
Route::get('segments/{segment}/customers', [SegmentController::class, 'customers']);
Route::post('segments/{segment}/recommendations', [RecommendationController::class, 'generate'])->middleware('throttle:20,1');

Route::get('customers/{customer}', [CustomerController::class, 'show']);

Route::get('recommendations', [RecommendationController::class, 'index']);
Route::get('recommendations/{recommendation}', [RecommendationController::class, 'show']);
Route::get('ai/usage', [RecommendationController::class, 'usage']);
