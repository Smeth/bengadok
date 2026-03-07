<?php

namespace Database\Seeders;

use App\Models\Pharmacie;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $pharma1 = Pharmacie::first();

        $users = [
            [
                'email' => 'superadmin@bengadok.cg',
                'name' => 'Super Admin',
                'role' => 'super_admin',
            ],
            [
                'email' => 'admin@bengadok.cg',
                'name' => 'Admin BengaDok',
                'role' => 'admin',
            ],
            [
                'email' => 'gerant@bengadok.cg',
                'name' => 'Gérant Pharmacie',
                'role' => 'gerant',
                'pharmacie_id' => $pharma1->id,
            ],
            [
                'email' => 'vendeur@bengadok.cg',
                'name' => 'Vendeur Pharma',
                'role' => 'vendeur',
                'pharmacie_id' => $pharma1->id ?? 1,
            ],
            [
                'email' => 'agent@bengadok.cg',
                'name' => 'Agent Call Center',
                'role' => 'agent_call_center',
            ],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'username' => $u['email'],
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                    'pharmacie_id' => $u['pharmacie_id'] ?? null,
                ]
            );
            $user->syncRoles([$u['role']]);
        }
    }
}
