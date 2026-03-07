<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Réinitialiser le cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Super Admin
            'gérer-tout',
            'gérer-rôles-pharmacies',
            'gérer-rôles-backoffice',
            // Admin
            'visualiser-donnees',
            'gérer-commandes',
            'gérer-catalogue',
            'stats-médicaments',
            'gérer-clients',
            'gérer-doublons',
            'stats-clients',
            'gérer-pharmacies',
            'stats-pharmacies',
            // Gérant pharmacie
            'créer-vendeurs',
            'historique-pharmacie',
            'confirmer-disponibilite',
            'stats-commandes-pharmacie',
            // Agent call center
            'reception-commande',
            'recherche-prix-pharmacie',
            'transmission-livreur',
            'renvoyer-pharmacie',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($permissions);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(array_diff($permissions, ['gérer-rôles-pharmacies', 'gérer-rôles-backoffice']));

        $gerant = Role::firstOrCreate(['name' => 'gerant', 'guard_name' => 'web']);
        $gerant->syncPermissions([
            'gérer-commandes',
            'créer-vendeurs',
            'historique-pharmacie',
            'confirmer-disponibilite',
            'stats-commandes-pharmacie',
        ]);

        $vendeur = Role::firstOrCreate(['name' => 'vendeur', 'guard_name' => 'web']);
        $vendeur->syncPermissions(['confirmer-disponibilite']);

        $agent = Role::firstOrCreate(['name' => 'agent_call_center', 'guard_name' => 'web']);
        $agent->syncPermissions([
            'reception-commande',
            'recherche-prix-pharmacie',
            'transmission-livreur',
            'renvoyer-pharmacie',
        ]);
    }
}
