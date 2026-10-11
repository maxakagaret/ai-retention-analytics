<?php

namespace App\Analytics\Synthetic;

use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Generates a reproducible customer base with known behaviour profiles, so the
 * analytics can be checked against ground truth:
 *
 *  loyal            — steady rhythm for the whole history
 *  fading           — intervals stretch visit after visit
 *  churned          — regular, then stopped 4–14 months ago
 *  recently_churned — regular, then stopped 2–4 months ago (the ones worth saving)
 *  new              — joined in the last ~3 months
 *  one_off          — a single visit long ago
 *  seasonal         — comes only in certain months
 *
 * Names and emails are fake (example.test). Everything is seeded: the same seed
 * gives the same dataset, which keeps tests and screenshots stable.
 */
class SyntheticDataGenerator
{
    private const PROFILES = [
        'loyal' => 22, 'fading' => 15, 'churned' => 18, 'recently_churned' => 10,
        'new' => 12, 'one_off' => 15, 'seasonal' => 8,
    ];

    private const SERVICES = [
        ['Haircut', 'hair', 35, 30], ['Coloring', 'hair', 90, 60], ['Styling', 'hair', 45, 21],
        ['Manicure', 'nails', 30, 21], ['Pedicure', 'nails', 40, 30],
        ['Facial', 'skin', 70, 45], ['Chemical peel', 'skin', 110, 90],
        ['Brow shaping', 'brows', 25, 28], ['Lash lift', 'brows', 60, 50],
    ];

    private const FIRST = ['Ada', 'Ben', 'Cleo', 'Dan', 'Eva', 'Finn', 'Gia', 'Hugo', 'Iris', 'Jon', 'Kai', 'Lena', 'Milo', 'Nora', 'Otto', 'Pia', 'Quin', 'Rosa', 'Sam', 'Tara', 'Uma', 'Vic', 'Wren', 'Yara', 'Zoe'];

    private const LAST = ['Archer', 'Brooks', 'Carter', 'Dalton', 'Ellis', 'Foster', 'Grant', 'Hayes', 'Irwin', 'Jensen', 'Keller', 'Lowe', 'Morgan', 'Nash', 'Owens', 'Parker', 'Reed', 'Stone', 'Turner', 'Vance', 'Walsh', 'Young'];

    /** @return array{customers: int, visits: int} */
    public function generate(int $customers, CarbonImmutable $today, int $seed = 2026): array
    {
        mt_srand($seed);

        $services = $this->seedServices();
        $byCategory = $services->groupBy('category');

        $customerRows = [];
        $visitRows = [];
        $lineRows = [];
        $customerId = (int) DB::table('customers')->max('id');
        $visitId = (int) DB::table('visits')->max('id');

        for ($i = 0; $i < $customers; $i++) {
            $customerId++;
            $profile = $this->pickProfile();
            $favorite = $byCategory->keys()[mt_rand(0, $byCategory->count() - 1)];
            [$joined, $dates] = $this->visitDates($profile, $today);

            $first = self::FIRST[mt_rand(0, count(self::FIRST) - 1)];
            $last = self::LAST[mt_rand(0, count(self::LAST) - 1)];
            $customerRows[] = [
                'id' => $customerId,
                'name' => "{$first} {$last}",
                'email' => strtolower("{$first}.{$last}.{$customerId}@example.test"),
                'joined_on' => $joined->toDateString(),
                'synthetic_profile' => $profile,
                'created_at' => $today, 'updated_at' => $today,
            ];

            foreach ($dates as $date) {
                $visitId++;
                // mt_rand-based picks: Collection::random() is not seedable.
                $picked = [$this->pick($byCategory[$favorite])];
                if (mt_rand(1, 100) <= 30) {
                    $picked[] = $this->pick($services);
                }
                $total = 0;
                foreach (collect($picked)->unique('id') as $service) {
                    $price = round($service->price * mt_rand(90, 115) / 100, 2);
                    $total += $price;
                    $lineRows[] = ['visit_id' => $visitId, 'service_id' => $service->id, 'price' => $price];
                }
                $visitRows[] = ['id' => $visitId, 'customer_id' => $customerId, 'visited_on' => $date->toDateString(), 'total' => $total];
            }
        }

        foreach (array_chunk($customerRows, 500) as $chunk) {
            DB::table('customers')->insert($chunk);
        }
        foreach (array_chunk($visitRows, 1000) as $chunk) {
            DB::table('visits')->insert($chunk);
        }
        foreach (array_chunk($lineRows, 1000) as $chunk) {
            DB::table('visit_services')->insert($chunk);
        }

        return ['customers' => count($customerRows), 'visits' => count($visitRows)];
    }

    private function seedServices()
    {
        if (Service::count() === 0) {
            Service::insert(array_map(fn ($s) => [
                'name' => $s[0], 'category' => $s[1], 'price' => $s[2], 'typical_interval_days' => $s[3],
            ], self::SERVICES));
        }

        return Service::all();
    }

    private function pick($collection)
    {
        $values = $collection->values();

        return $values[mt_rand(0, $values->count() - 1)];
    }

    private function pickProfile(): string
    {
        $roll = mt_rand(1, array_sum(self::PROFILES));
        foreach (self::PROFILES as $profile => $weight) {
            if (($roll -= $weight) <= 0) {
                return $profile;
            }
        }

        return 'loyal';
    }

    /** @return array{0: CarbonImmutable, 1: list<CarbonImmutable>} join date and visit dates (ascending, <= today) */
    private function visitDates(string $profile, CarbonImmutable $today): array
    {
        $interval = mt_rand(21, 50);

        [$start, $stop, $stretch] = match ($profile) {
            'loyal' => [$today->subDays(mt_rand(240, 720)), $today, 1.0],
            'fading' => [$today->subDays(mt_rand(400, 720)), $today, 1.18],
            'churned' => [$today->subDays(mt_rand(500, 720)), $today->subDays(mt_rand(120, 420)), 1.0],
            'recently_churned' => [$today->subDays(mt_rand(300, 600)), $today->subDays(mt_rand(60, 120)), 1.0],
            'new' => [$today->subDays(mt_rand(10, 90)), $today, 1.0],
            'one_off' => [$today->subDays(mt_rand(90, 540)), null, 1.0],
            'seasonal' => [$today->subDays(mt_rand(400, 720)), $today, 1.0],
        };

        if ($profile === 'one_off') {
            return [$start, [$start]];
        }

        $dates = [];
        $date = $start;
        while ($date->lte($stop) && $date->lte($today)) {
            if ($profile !== 'seasonal' || in_array($date->month, [3, 4, 5, 11, 12], true)) {
                $dates[] = $date;
            }
            $jitter = mt_rand(80, 120) / 100;
            $date = $date->addDays(max(7, (int) round($interval * $jitter)));
            $interval = (int) round($interval * $stretch);
            if ($profile === 'new' && count($dates) >= mt_rand(1, 3)) {
                break;
            }
        }

        return [$start, $dates ?: [$start]];
    }
}
