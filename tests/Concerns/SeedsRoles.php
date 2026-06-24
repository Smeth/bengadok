<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

trait SeedsRoles
{
    protected function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create($attributes);
        $user->assignRole(Role::findByName($role, 'web'));

        return $user;
    }
}
