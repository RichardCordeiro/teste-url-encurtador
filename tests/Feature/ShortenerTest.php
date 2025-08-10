<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('creates a short link with minutes and validates url', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('links.store'), [
            'original_url' => 'https://example.com',
            'expires_in_minutes' => 5,
        ]);

    $response->assertRedirect(route('links.index'));

    $this->assertDatabaseHas('links', [
        'user_id' => $user->id,
        'original_url' => 'https://example.com',
        'status' => 'active',
    ]);
});

it('increments clicks and records visit on redirect', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $user->id,
        'original_url' => 'https://laravel.com',
        'slug' => 'abc123',
        'status' => 'active',
        'expires_at' => null,
        'click_count' => 0,
    ]);

    $this->get('/s/'.$link->slug)->assertRedirect('https://laravel.com');

    $this->assertDatabaseHas('links', [
        'id' => $link->id,
        'click_count' => 1,
    ]);

    $this->assertDatabaseCount('visits', 1);
});

it('returns expired page when past expires_at', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create([
        'user_id' => $user->id,
        'original_url' => 'https://laravel.com',
        'slug' => 'abc124',
        'status' => 'active',
        'expires_at' => now()->subMinute(),
    ]);

    $this->get('/s/'.$link->slug)
        ->assertStatus(410)
        ->assertSee('Link expirado');
});


