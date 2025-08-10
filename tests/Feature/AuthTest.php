<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Teste',
            'email' => 'teste@example.com',
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', ['email' => 'teste@example.com']);
    }

    public function test_user_can_login_and_logout()
    {
        $user = User::factory()->create([
            'password' => bcrypt('senha123')
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha123'
        ]);
        $response->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $response = $this->post('/logout');
        $response->assertRedirect('/');
        $this->assertGuest();
    }
}