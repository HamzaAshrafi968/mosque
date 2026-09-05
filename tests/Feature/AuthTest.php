<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_returns_token(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Tenant::factory()->create();
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->postJson('/api/v1/register', [
            'mosque_name' => 'جامع جديد',
            'name' => 'مستخدم',
            'email' => 'new@example.com',
            'password' => 'password',
        ])->assertNotFound();
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_login_assigns_default_roles(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->for($tenant)->create();

        $this->assertTrue($admin->hasRole('mosque_manager'));
        $this->assertTrue($admin->isAdmin());

        $teacher = User::factory()->for($tenant)->create();
        $this->assertTrue($teacher->hasRole('teacher'));
    }
}
