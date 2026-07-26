<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_register_creates_tenant_and_admin_with_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'mosque_name' => 'جامع النور',
            'name' => 'مدير الجامع',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'gender' => 'male',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['success', 'data' => ['user' => ['id', 'name', 'email', 'role', 'gender'], 'token']])
            ->assertJsonPath('data.user.role', 'admin');

        $this->assertDatabaseHas('tenants', ['name' => 'جامع النور']);
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'role' => 'admin']);
    }

    public function test_login_returns_token(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['user', 'token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }
}
