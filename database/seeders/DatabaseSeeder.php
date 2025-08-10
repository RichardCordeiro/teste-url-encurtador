<?php

namespace Database\Seeders;

use App\Models\Link;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')]
        );


        $totalLinksTarget = 5000;
        $existingLinks = Link::count();
        $linksToCreate = max(0, $totalLinksTarget - $existingLinks);

        if ($linksToCreate > 0) {
            Link::factory()->count($linksToCreate)->create([
                'user_id' => $user->id,
            ]);
        }

        $linkIds = Link::pluck('id')->all();
        if (empty($linkIds)) {
            return;
        }


        $totalVisitsTarget = 100000;
        $batchSize = 5000;
        $visitsData = [];

        for ($i = 0; $i < $totalVisitsTarget; $i++) {
            $daysAgo = random_int(0, 29);
            $createdAt = now()
                ->copy()
                ->subDays($daysAgo)
                ->subMinutes(random_int(0, 1440));

            $ip = sprintf('%d.%d.%d.%d', random_int(1, 223), random_int(0, 255), random_int(0, 255), random_int(1, 254));
            $visitsData[] = [
                'link_id' => Arr::random($linkIds),
                'ip_hash' => hash('sha256', $ip),
                'user_agent' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($visitsData) >= $batchSize) {
                DB::table('visits')->insert($visitsData);
                $visitsData = [];
            }
        }

        if (!empty($visitsData)) {
            DB::table('visits')->insert($visitsData);
        }

        
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('UPDATE links l LEFT JOIN (SELECT link_id, COUNT(*) c FROM visits GROUP BY link_id) v ON v.link_id = l.id SET l.click_count = COALESCE(v.c, 0)');
        } elseif ($driver === 'pgsql') {
            DB::statement('UPDATE links SET click_count = v.c FROM (SELECT link_id, COUNT(*) AS c FROM visits GROUP BY link_id) AS v WHERE v.link_id = links.id');
            DB::statement('UPDATE links SET click_count = 0 WHERE NOT EXISTS (SELECT 1 FROM visits WHERE visits.link_id = links.id)');
        } else {
            $counts = DB::table('visits')->select('link_id', DB::raw('COUNT(*) as c'))->groupBy('link_id')->get()->keyBy('link_id');
            foreach (Link::cursor() as $link) {
                $link->click_count = (int) ($counts[$link->id]->c ?? 0);
                $link->save();
            }
        }
    }
}