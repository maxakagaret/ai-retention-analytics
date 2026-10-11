<?php

namespace Database\Seeders;

use App\Ai\Recommendations\RecommendationService;
use App\Analytics\AnalysisService;
use App\Analytics\Backtest\Backtester;
use App\Analytics\Synthetic\SyntheticDataGenerator;
use App\Enums\Segment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Synthetic customer base + the analyses a real deployment would have built up:
 * two monthly snapshots (for the segment-migration view), a backtest and AI
 * recommendations for the retention-priority segments.
 */
class DemoSeeder extends Seeder
{
    public function run(SyntheticDataGenerator $generator, AnalysisService $analysis, Backtester $backtester, RecommendationService $recommendations): void
    {
        $today = CarbonImmutable::today();
        $customers = (int) config('retention.demo_customers');

        $stats = $generator->generate($customers, $today);
        $this->command?->info("Generated {$stats['customers']} customers and {$stats['visits']} visits.");

        $analysis->run($today->subDays(30));
        $latest = $analysis->run($today);
        $backtester->run($today);

        // Recommendations for the non-empty segments that matter most for retention.
        collect([Segment::CantLoseThem, Segment::AtRisk, Segment::NeedAttention, Segment::NewCustomers])
            ->filter(fn (Segment $s) => ($latest->summary['segments'][$s->value]['customers'] ?? 0) > 0)
            ->take(3)
            ->each(fn (Segment $s) => $recommendations->generate($latest, $s));
    }
}
