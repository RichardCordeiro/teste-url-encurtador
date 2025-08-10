<?php

namespace Database\Factories;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    protected $model = Link::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_url' => $this->faker->url(),
            'slug' => rtrim(strtr(base64_encode(random_bytes(6)), '+/', '-_'), '='),
            'status' => 'active',
            'expires_at' => null,
            'click_count' => 0,
        ];
    }
}


