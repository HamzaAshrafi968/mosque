<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CsrfSessionExpiryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post('/_test/token-mismatch', fn () => throw new TokenMismatchException);
    }

    public function test_expired_token_sends_guests_back_to_login_with_a_message(): void
    {
        $this->post('/_test/token-mismatch')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_expired_token_sends_authenticated_users_back_to_their_page(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user)
            ->from('/admin/dashboard')
            ->post('/_test/token-mismatch')
            ->assertRedirect('/admin/dashboard')
            ->assertSessionHasErrors('session');
    }

    public function test_expired_token_returns_json_for_api_requests(): void
    {
        $this->postJson('/_test/token-mismatch')
            ->assertStatus(419)
            ->assertJsonStructure(['message']);
    }

    public function test_web_pages_disable_browser_caching(): void
    {
        $response = $this->get('/login')->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $response->assertHeader('Pragma', 'no-cache');
    }
}
