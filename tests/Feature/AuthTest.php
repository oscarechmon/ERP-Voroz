<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $this->seedErp();
        $user = User::factory()->create(['email' => 'a@test.com', 'password' => Hash::make('secret123'), 'is_active' => true]);
        $user->assignRole('Ventas');

        $this->postJson('/api/v1/auth/login', ['email' => 'a@test.com', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('data.email', 'a@test.com')
            ->assertJsonPath('data.roles.0', 'Ventas');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->seedErp();
        User::factory()->create(['email' => 'a@test.com', 'password' => Hash::make('secret123')]);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@test.com', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->seedErp();
        User::factory()->create(['email' => 'x@test.com', 'password' => Hash::make('secret123'), 'is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 'x@test.com', 'password' => 'secret123'])
            ->assertStatus(422);
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
