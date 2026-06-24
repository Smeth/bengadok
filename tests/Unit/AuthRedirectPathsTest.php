<?php

namespace Tests\Unit;

use App\Support\AuthRedirectPaths;
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

    public function test_gerant_can_access_dashboard_but_not_clients_doublons(): void
    {
        $user = $this->userWithRole('gerant');

        $this->assertTrue(AuthRedirectPaths::pathAllowedForUser($user, '/dashboard'));
        $this->assertFalse(AuthRedirectPaths::pathAllowedForUser($user, '/clients/doublons'));
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
}
