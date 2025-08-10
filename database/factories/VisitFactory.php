<?php

namespace Database\Factories;

use App\Models\Visit;
use App\Models\Link;
use Illuminate\Database\Eloquent\Factories\Factory;
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'link_id' => Link::factory(),
            'ip_hash' => hash('sha256', $this->faker->ipv4()),
            'user_agent' => $this->faker->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}


