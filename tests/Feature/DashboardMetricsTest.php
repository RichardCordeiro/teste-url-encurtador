<?php

use App\Models\Link;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns metrics summary and top links for 7 days', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $link1 = Link::factory()->create(['user_id' => $user->id, 'slug' => 'l1']);
    $link2 = Link::factory()->create(['user_id' => $user->id, 'slug' => 'l2']);
    $foreignLink = Link::factory()->create(['user_id' => $other->id, 'slug' => 'l3']);

    
    Visit::factory()->for($link1)->count(3)->create();
    Visit::factory()->for($link2)->count(1)->create();

    Visit::factory()->for($link2)->create(['created_at' => now()->subDays(40)]);


    Visit::factory()->for($foreignLink)->count(10)->create();

    $summary = $this->actingAs($user)->getJson(route('metrics.summary', ['period' => '7d']))
        ->assertOk()
        ->json();

    expect($summary['total_links'])->toBe(2)
        ->and($summary['active_links'])->toBe(2)
        ->and($summary['expired_links'])->toBe(0)
        ->and($summary['total_clicks_in_period'])->toBe(4);

    $top = $this->actingAs($user)->getJson(route('metrics.top', ['period' => '7d']))
        ->assertOk()
        ->json('top');

    expect($top)->toBeArray()
        ->and($top[0]['slug'])->toBe('l1')
        ->and($top[0]['clicks'])->toBe(3)
        ->and(collect($top)->pluck('slug')->contains('l3'))->toBeFalse();
});


