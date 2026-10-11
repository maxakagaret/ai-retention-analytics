<?php

use App\Models\Customer;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

function service(string $name = 'Haircut', int $interval = 30, float $price = 40): Service
{
    return Service::firstOrCreate(['name' => $name], ['category' => 'hair', 'price' => $price, 'typical_interval_days' => $interval]);
}

/**
 * A customer with visits N days before "today" (2026-03-02 in tests).
 *
 * @param  list<int>  $daysAgo
 */
function customerVisitedDaysAgo(array $daysAgo, float $total = 50, ?Service $service = null, string $profile = 'test'): Customer
{
    $customer = Customer::create([
        'name' => 'Test '.Str::random(5),
        'email' => Str::lower(Str::random(8)).'@example.test',
        'joined_on' => CarbonImmutable::today()->subDays(max($daysAgo ?: [0]))->toDateString(),
        'synthetic_profile' => $profile,
    ]);
    $service ??= service();

    foreach ($daysAgo as $days) {
        $visitId = DB::table('visits')->insertGetId([
            'customer_id' => $customer->id,
            'visited_on' => CarbonImmutable::today()->subDays($days)->toDateString(),
            'total' => $total,
        ]);
        DB::table('visit_services')->insert(['visit_id' => $visitId, 'service_id' => $service->id, 'price' => $total]);
    }

    return $customer;
}

/** Days-ago values for visits every $interval days, from $firstDaysAgo down to $lastDaysAgo. */
function regularVisits(int $firstDaysAgo, int $lastDaysAgo, int $interval): array
{
    return range($firstDaysAgo, $lastDaysAgo, -$interval);
}
