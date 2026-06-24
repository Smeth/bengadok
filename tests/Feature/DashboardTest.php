<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRoles;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = $this->userWithRole('admin');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
    }
}
