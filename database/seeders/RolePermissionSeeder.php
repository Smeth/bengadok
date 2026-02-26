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
            'visualiser-donnees',      // Gérant: commandes, revenus, médicaments
            'gérer-commandes',         // Gérant: liste, statistiques
            'confirmer-disponibilite', // Vendeur: confirmer médicaments + prix
            'reception-commande',     // Agent: réception commande
            'recherche-prix-pharmacie', // Agent: recherche prix/dispo
            'transmission-livreur',    // Agent: transmission commande livreur
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $gerant = Role::firstOrCreate(['name' => 'gerant', 'guard_name' => 'web']);
        $gerant->syncPermissions(['visualiser-donnees', 'gérer-commandes']);

        $vendeur = Role::firstOrCreate(['name' => 'vendeur', 'guard_name' => 'web']);
        $vendeur->syncPermissions(['confirmer-disponibilite']);

        $agent = Role::firstOrCreate(['name' => 'agent_call_center', 'guard_name' => 'web']);
        $agent->syncPermissions(['reception-commande', 'recherche-prix-pharmacie', 'transmission-livreur']);
    }
}
