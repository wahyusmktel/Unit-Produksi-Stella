<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_is_public(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
    }

    public function test_dashboard_redirects_guests_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_sso_redirect_uses_pkce(): void
    {
        config()->set('sso.base_url', 'https://sso.test');
        config()->set('sso.client_id', 'client-123');
        config()->set('sso.redirect_uri', 'https://up.test/auth/sisfo/callback');

        $response = $this->get(route('sso.redirect'));

        $response->assertRedirectContains('https://sso.test/oauth/authorize?');
        $response->assertRedirectContains('code_challenge_method=S256');
        $response->assertSessionHas('sso_state');
        $response->assertSessionHas('sso_code_verifier');
    }

    public function test_valid_sso_callback_creates_local_session(): void
    {
        config()->set('sso.base_url', 'https://sso.test');
        config()->set('sso.client_id', 'client-123');
        config()->set('sso.redirect_uri', 'https://up.test/auth/sisfo/callback');

        Http::fake([
            'https://sso.test/oauth/token' => Http::response(['access_token' => 'access-token']),
            'https://sso.test/api/sso/user' => Http::response([
                'sub' => 'sisfo-user-8',
                'name' => 'Wahyu Rahman',
                'email' => 'wahyu@example.test',
                'email_verified' => true,
                'picture' => 'https://example.test/avatar.jpg',
                'roles' => ['guru kelas'],
            ]),
        ]);

        $response = $this
            ->withSession(['sso_state' => 'expected-state', 'sso_code_verifier' => 'verifier'])
            ->get(route('sso.callback', ['state' => 'expected-state', 'code' => 'authorization-code']));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'sso_subject' => 'sisfo-user-8',
            'email' => 'wahyu@example.test',
        ]);
    }

    public function test_logout_invalidates_local_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
