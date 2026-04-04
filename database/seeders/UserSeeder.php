<?php

namespace Database\Seeders;

use App\Models\Pharmacie;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $pharma1 = Pharmacie::query()->orderBy('id')->first();

        $users = [
            [
                'email' => 'superadmin@bengadok.cg',
                'name' => 'Super Admin',
                'role' => 'super_admin',
                'phone' => '+242 06 000 00 00',
            ],
            [
                'email' => 'admin@bengadok.cg',
                'name' => 'Admin BengaDok',
                'role' => 'admin',
                'phone' => '+242 06 000 00 01',
            ],
            [
                'email' => 'gerant@bengadok.cg',
                'name' => 'Gérant Pharmacie',
                'role' => 'gerant',
                'pharmacie_id' => $pharma1?->id,
                'phone' => '+242 06 000 00 02',
            ],
            [
                'email' => 'vendeur@bengadok.cg',
                'name' => 'Vendeur Pharma',
                'role' => 'vendeur',
                'pharmacie_id' => $pharma1?->id,
                'phone' => '+242 06 000 00 03',
            ],
            [
                'email' => 'agent@bengadok.cg',
                'name' => 'Agent Call Center',
                'role' => 'agent_call_center',
                'phone' => '+242 06 000 00 04',
            ],
            [
                'email' => 'support@bengadok.cg',
                'name' => 'Support BengaDok',
                'role' => 'agent_call_center',
                'phone' => '+242 06 000 00 05',
            ],
            [
                'email' => 'coordination@bengadok.cg',
                'name' => 'Coordination',
                'role' => 'admin',
                'phone' => '+242 06 000 00 06',
            ],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'username' => $u['email'],
                    'phone' => $u['phone'] ?? null,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                    'pharmacie_id' => $u['pharmacie_id'] ?? null,
                ]
            );
            $user->syncRoles([$u['role']]);
        }
    }
}
