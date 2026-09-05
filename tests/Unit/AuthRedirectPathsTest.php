<?php

namespace Tests\Unit;

use App\Support\AuthRedirectPaths;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class AuthRedirectPathsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    public function test_vendeur_home_is_dok_pharma_commandes(): void
    {
        $user = $this->userWithRole('vendeur');

        $this->assertSame('/dok-pharma/commandes', AuthRedirectPaths::homeForUser($user));
    }

    public function test_vendeur_cannot_access_clients_doublons(): void
    {
        $user = $this->userWithRole('vendeur');

        $this->assertFalse(AuthRedirectPaths::pathAllowedForUser($user, '/clients/doublons'));
        $this->assertTrue(AuthRedirectPaths::pathAllowedForUser($user, '/dok-pharma/commandes'));
    }

    public function test_gerant_cannot_access_dashboard_or_clients_doublons(): void
    {
        $user = $this->userWithRole('gerant');

        $this->assertFalse(AuthRedirectPaths::pathAllowedForUser($user, '/dashboard'));
        $this->assertFalse(AuthRedirectPaths::pathAllowedForUser($user, '/clients/doublons'));
        $this->assertTrue(AuthRedirectPaths::pathAllowedForUser($user, '/dok-pharma'));
    }

    public function test_resolve_destination_filters_unauthorized_intended_url(): void
    {
        $user = $this->userWithRole('vendeur');

        $request = Request::create('/email/verify', 'GET');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('url.intended', '/clients/doublons');

        $this->assertSame(
            '/dok-pharma/commandes',
            AuthRedirectPaths::resolveDestination($request, $user),
        );
    }

    public function test_gerant_intended_legacy_dashboard_falls_back_to_dok_pharma(): void
    {
        $user = $this->userWithRole('gerant');

        $request = Request::create('/email/verify', 'GET');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('url.intended', '/dashboard');

        $this->assertSame(
            '/dok-pharma',
            AuthRedirectPaths::resolveDestination($request, $user),
        );
    }

    public function test_user_without_role_home_is_profile_settings(): void
    {
        $user = User::factory()->create();

        $this->assertSame('/settings/profile', AuthRedirectPaths::homeForUser($user));
    }
}
