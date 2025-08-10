<?php

namespace Database\Seeders;

use App\Models\Link;
use App\Models\User;
use App\Models\Visit;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Create or get a test user
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')]
        );

        // Create a set of links for this user
        $numLinks = 1000;
        if (Link::where('user_id', $user->id)->count() < $numLinks) {
            Link::factory()->count($numLinks)->create([
                'user_id' => $user->id,
            ]);
        }

        $linkIds = Link::where('user_id', $user->id)->pluck('id')->all();

        // Create 5000 visits distributed randomly across the user's links
        Visit::factory()
            ->count(5000)
            ->state(function () use ($linkIds) {
                return [
                    'link_id' => Arr::random($linkIds),
                ];
            })
            ->create();

        // Distribute exactly 100000 clicks across the user's links
        $links = Link::whereIn('id', $linkIds)->get();
        $totalClicksTarget = 100000;
        $remaining = $totalClicksTarget;
        $totalLinks = $links->count();

        foreach ($links as $index => $link) {
            if ($index === $totalLinks - 1) {
                $assigned = $remaining;
            } else {
                // Keep distribution reasonable while ensuring the sum matches
                $maxForThisLink = max(0, (int) floor(($remaining / max(1, ($totalLinks - $index))) * 2));
                $assigned = $maxForThisLink > 0 ? random_int(0, min($maxForThisLink, $remaining)) : 0;
            }

            $link->click_count = $assigned;
            $link->save();

            $remaining -= $assigned;
        }
    }
}
