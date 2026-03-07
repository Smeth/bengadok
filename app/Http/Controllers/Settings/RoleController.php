<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /** Rôles agents pharmacie (avec pharmacie_id) */
    private const ROLES_PHARMACIE = ['gerant', 'vendeur'];

    /** Rôles utilisateurs backoffice */
    private const ROLES_BACKOFFICE = ['super_admin', 'admin', 'agent_call_center'];

    /** Permissions groupées par catégorie pour l'UI */
    private const PERMISSIONS_PAR_CATEGORIE = [
        'Commandes' => ['gérer-commandes', 'stats-commandes-pharmacie'],
        'Pharmacie' => ['gérer-pharmacies', 'gérer-rôles-pharmacies'],
        'Médicaments' => ['gérer-catalogue', 'stats-médicaments'],
        'Clients' => ['gérer-clients', 'gérer-doublons', 'stats-clients'],
        'Utilisateurs backoffice' => ['gérer-rôles-backoffice', 'visualiser-donnees', 'gérer-tout'],
        'Agent' => ['reception-commande', 'recherche-prix-pharmacie', 'transmission-livreur', 'renvoyer-pharmacie'],
        'Gérant' => ['créer-vendeurs', 'historique-pharmacie', 'confirmer-disponibilite'],
    ];

    private const PERMISSION_LABELS = [
        'gérer-tout' => 'Gérer tout',
        'gérer-rôles-pharmacies' => 'Rôles pharmacies',
        'gérer-rôles-backoffice' => 'Gérer les rôles du backoffice',
        'visualiser-donnees' => 'Visualiser les données',
        'gérer-commandes' => 'Gérer les commandes',
        'stats-commandes-pharmacie' => 'Consulter statistiques commandes',
        'gérer-catalogue' => 'Consulter catalogue Médicaments',
        'stats-médicaments' => 'Consulter statistiques Médicaments',
        'gérer-clients' => 'Consulter la liste des clients',
        'gérer-doublons' => 'Gérer les doublons clients',
        'stats-clients' => 'Consulter les statistiques clients',
        'gérer-pharmacies' => 'Gestion des pharmacies',
        'stats-pharmacies' => 'Consulter statistiques pharmacies',
        'créer-vendeurs' => 'Créer des vendeurs',
        'historique-pharmacie' => 'Historique pharmacie',
        'confirmer-disponibilite' => 'Confirmer disponibilité',
        'reception-commande' => 'Réception des commandes',
        'recherche-prix-pharmacie' => 'Recherche prix pharmacie',
        'transmission-livreur' => 'Transmission livreur',
        'renvoyer-pharmacie' => 'Renvoyer à une autre pharmacie',
    ];

    private const ROLE_LABELS = [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'agent_call_center' => 'Agent Call Center',
        'gerant' => 'Gérant',
        'vendeur' => 'Vendeur',
    ];

    private const ROLE_DESCRIPTIONS = [
        'super_admin' => 'Accès complet - Gestion du système et des rôles',
        'admin' => 'Gestion des pharmacies et des commandes',
        'agent_call_center' => 'Gestion des commandes et réceptions',
        'gerant' => 'Gestion de sa pharmacie, vendeurs, historiques',
        'vendeur' => 'Interface Dok Pharma pour traiter les commandes',
    ];

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $onglet = $request->input('onglet', 'backoffice');

        $rolesBackoffice = $this->getRoles(self::ROLES_BACKOFFICE, $search);
        $rolesPharmacie = $this->getRoles(self::ROLES_PHARMACIE, $search);

        $permissionsGroupées = $this->getPermissionsGroupées();
        $allPermissions = Permission::where('guard_name', 'web')->pluck('name')->all();

        return Inertia::render('settings/Roles/Index', [
            'rolesBackoffice' => $rolesBackoffice,
            'rolesPharmacie' => $rolesPharmacie,
            'permissionsGroupées' => $permissionsGroupées,
            'allPermissions' => $allPermissions,
            'permissionLabels' => self::PERMISSION_LABELS,
            'filters' => ['search' => $search, 'onglet' => $onglet],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'type' => 'required|in:backoffice,pharmacie',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $slug = $this->slugFromName($validated['name']);
        if (Role::where('name', $slug)->exists()) {
            return back()->withErrors(['name' => 'Un rôle avec ce nom existe déjà.']);
        }

        $role = Role::create([
            'name' => $slug,
            'guard_name' => 'web',
        ]);

        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('status', "Rôle {$role->name} créé.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('status', 'Rôle mis à jour.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->count() > 0) {
            return back()->with('error', 'Impossible de supprimer : des utilisateurs ont ce rôle.');
        }

        if (in_array($role->name, array_merge(self::ROLES_BACKOFFICE, self::ROLES_PHARMACIE))) {
            return back()->with('error', 'Les rôles système ne peuvent pas être supprimés.');
        }

        $role->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('status', 'Rôle supprimé.');
    }

    private function getRoles(array $roleNames, string $search): array
    {
        $query = Role::whereIn('name', $roleNames)
            ->where('guard_name', 'web')
            ->withCount('users')
            ->with('permissions');

        if ($search) {
            $searchLower = mb_strtolower($search);
            $matchingNames = array_keys(array_filter(self::ROLE_LABELS, fn ($l) => stripos($l, $search) !== false));
            $query->where(function ($q) use ($search, $matchingNames) {
                $q->where('name', 'like', "%{$search}%");
                if (!empty($matchingNames)) {
                    $q->orWhereIn('name', $matchingNames);
                }
            });
        }

        $roles = $query->get()->map(fn (Role $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'label' => self::ROLE_LABELS[$r->name] ?? $r->name,
            'description' => self::ROLE_DESCRIPTIONS[$r->name] ?? 'Accès aux fonctionnalités du logiciel',
            'permissions_count' => $r->permissions->count(),
            'users_count' => $r->users_count,
            'permissions' => $this->sortPermissionsForDisplay($r->permissions),
            'is_system' => in_array($r->name, array_merge(self::ROLES_BACKOFFICE, self::ROLES_PHARMACIE)),
        ])->values()->all();

        return $this->sortRolesByOrder($roles, $roleNames);
    }

    private function getPermissionsGroupées(): array
    {
        $result = [];
        foreach (self::PERMISSIONS_PAR_CATEGORIE as $cat => $perms) {
            $items = [];
            foreach ($perms as $p) {
                $label = self::PERMISSION_LABELS[$p] ?? $p;
                if (Permission::where('name', $p)->exists()) {
                    $items[] = ['name' => $p, 'label' => $label];
                }
            }
            if (!empty($items)) {
                $result[$cat] = $items;
            }
        }
        return $result;
    }

    private function groupPermissionsByCategory(array $permissionNames): array
    {
        $groupées = [];
        foreach (self::PERMISSIONS_PAR_CATEGORIE as $cat => $perms) {
            $found = array_intersect($perms, $permissionNames);
            if (!empty($found)) {
                $groupées[$cat] = array_map(fn ($n) => self::PERMISSION_LABELS[$n] ?? $n, $found);
            }
        }
        return $groupées;
    }

    private function slugFromName(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($name)));
    }

    /** Trie les rôles selon l'ordre défini (Super Admin en premier, etc.) */
    private function sortRolesByOrder(array $roles, array $roleNames): array
    {
        $order = array_flip($roleNames);
        usort($roles, fn ($a, $b) => ($order[$a['name']] ?? 999) <=> ($order[$b['name']] ?? 999));
        return $roles;
    }

    /** Trie les permissions pour l'affichage : "gérer-tout" en premier si présent */
    private function sortPermissionsForDisplay($permissions): array
    {
        $sorted = $permissions->sortBy(function ($p) {
            if ($p->name === 'gérer-tout') {
                return 0;
            }
            return 1;
        });

        return $sorted->values()->map(fn ($p) => [
            'name' => $p->name,
            'label' => self::PERMISSION_LABELS[$p->name] ?? $p->name,
        ])->values()->all();
    }
}
