<?php

namespace Tests\Feature\Auth;

use App\Support\AuthRedirectPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class BackofficeAccessTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    /** @return list<array{0: string, 1: string}> */
    public static function blockedBackofficePathsForVendeur(): array
    {
        return [
            ['/clients/doublons', '/dok-pharma/commandes'],
            ['/clients', '/dok-pharma/commandes'],
            ['/medicaments/doublons', '/dok-pharma/commandes'],
            ['/commandes', '/dok-pharma/commandes'],
            ['/dashboard', '/dok-pharma/commandes'],
        ];
    }

    /** @dataProvider blockedBackofficePathsForVendeur */
    public function test_vendeur_is_redirected_away_from_backoffice(string $path, string $expectedHome): void
    {
        $user = $this->userWithRole('vendeur');

        $this->actingAs($user)
            ->get($path)
            ->assertRedirect($expectedHome);
    }

    public function test_gerant_is_redirected_from_clients_doublons_but_can_open_dashboard(): void
    {
        $gerant = $this->userWithRole('gerant');

        $this->actingAs($gerant)
            ->get('/clients/doublons')
            ->assertRedirect('/dok-pharma');

        $this->actingAs($gerant)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_login_with_intended_backoffice_url_redirects_vendeur_to_pharmacy_home(): void
    {
        $user = $this->userWithRole('vendeur');

        $this->get('/clients/doublons')->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('post-login.loading', absolute: false));

        $this->actingAs($user)
            ->get(route('post-login.loading'))
            ->assertInertia(fn ($page) => $page
                ->component('auth/PostLoginLoading')
                ->where('redirectTo', '/dok-pharma/commandes')
            );
    }

    public function test_backoffice_routes_are_guarded_by_middleware(): void
    {
        $prefixes = AuthRedirectPaths::backofficePathPrefixes();

        foreach (Route::getRoutes() as $route) {
            $uri = '/'.$route->uri();
            $isBackoffice = false;

            foreach ($prefixes as $prefix) {
                if ($uri === $prefix || str_starts_with($uri, $prefix.'/')) {
                    $isBackoffice = true;
                    break;
                }
            }

            if (! $isBackoffice) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            $this->assertContains(
                'backoffice',
                $middleware,
                "La route {$uri} doit être protégée par le middleware backoffice.",
            );
        }
    }
}
