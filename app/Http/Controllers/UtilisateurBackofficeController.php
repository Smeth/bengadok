<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UtilisateurBackofficeController extends Controller
{
    /** Rôles backoffice (sans pharmacie) */
    private const ROLES_BACKOFFICE = ['super_admin', 'admin', 'agent_call_center'];

    /** Labels des permissions pour l'affichage */
    private const PERMISSION_LABELS = [
        'gérer-tout' => 'Gérer tout',
        'gérer-rôles-pharmacies' => 'Rôles pharmacies',
        'gérer-rôles-backoffice' => 'Gérer les rôles du backoffice',
        'visualiser-donnees' => 'Visualiser les données',
        'gérer-commandes' => 'Gérer les commandes',
        'gérer-catalogue' => 'Consulter catalogue Médicaments',
        'stats-médicaments' => 'Consulter statistiques Médicaments',
        'gérer-clients' => 'Consulter la liste des clients',
        'gérer-doublons' => 'Gérer les doublons clients',
        'stats-clients' => 'Consulter les statistiques clients',
        'gérer-pharmacies' => 'Gestion des pharmacies',
        'stats-pharmacies' => 'Consulter statistiques pharmacies',
        'reception-commande' => 'Réception des commandes',
        'recherche-prix-pharmacie' => 'Recherche prix pharmacie',
        'transmission-livreur' => 'Transmission livreur',
        'renvoyer-pharmacie' => 'Renvoyer à une autre pharmacie',
    ];

    public function index(Request $request): Response
    {
        $this->authorizeBackoffice();

        $query = User::whereNull('pharmacie_id')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::ROLES_BACKOFFICE));

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->with('roles.permissions', 'permissions')->orderBy('name')->get();

        $roles = Role::whereIn('name', self::ROLES_BACKOFFICE)
            ->with('permissions')
            ->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'label' => $this->roleLabel($r->name),
                'description' => $this->roleDescription($r->name),
                'permissions' => $r->permissions->map(fn ($p) => [
                    'name' => $p->name,
                    'label' => self::PERMISSION_LABELS[$p->name] ?? $p->name,
                ])->values()->all(),
            ]);

        $permissionsGroupées = $this->getPermissionsGroupées();

        return Inertia::render('Utilisateurs/Index', [
            'users' => $users->map(fn (User $u) => $this->formatUser($u)),
            'roles' => $roles,
            'permissionLabels' => self::PERMISSION_LABELS,
            'permissionsGroupées' => $permissionsGroupées,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeBackoffice();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'username' => 'nullable|string|max:50|unique:users,username',
            'password' => ['required', Password::defaults()],
            'role' => 'required|in:' . implode(',', self::ROLES_BACKOFFICE),
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'] ?? null,
            'password' => Hash::make($validated['password']),
            'pharmacie_id' => null,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($validated['role']);

        return back()->with('status', "Utilisateur {$user->name} créé.");
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeBackoffice();
        $this->ensureBackofficeUser($user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'username' => 'nullable|string|max:50|unique:users,username,' . $user->id,
            'role' => 'required|in:' . implode(',', self::ROLES_BACKOFFICE),
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'] ?? null,
        ]);
        $user->syncRoles([$validated['role']]);

        return back()->with('status', 'Utilisateur mis à jour.');
    }

    public function destroy(User $user)
    {
        $this->authorizeBackoffice();
        $this->ensureBackofficeUser($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $user->delete();
        return back()->with('status', 'Utilisateur supprimé.');
    }

    public function updatePermissions(Request $request, User $user)
    {
        $this->authorizeBackoffice();
        $this->ensureBackofficeUser($user);

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $user->syncPermissions($validated['permissions']);

        return back()->with('status', 'Permissions mises à jour.');
    }

    private function authorizeBackoffice(): void
    {
        if (!auth()->user()?->hasAnyRole(['admin', 'super_admin'])) {
            abort(403);
        }
    }

    private function ensureBackofficeUser(User $user): void
    {
        if ($user->pharmacie_id || !$user->hasAnyRole(self::ROLES_BACKOFFICE)) {
            abort(404);
        }
    }

    private function roleLabel(string $name): string
    {
        return match ($name) {
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'agent_call_center' => 'Agent Call Center',
            default => $name,
        };
    }

    private function roleDescription(string $name): string
    {
        return match ($name) {
            'super_admin' => 'Accès complet - Gestion du système',
            'admin' => 'Gestion des pharmacies et des commandes',
            'agent_call_center' => 'Gestion des commandes et réceptions',
            default => '',
        };
    }

    private function getPermissionsGroupées(): array
    {
        $groupes = [
            'Commandes' => ['gérer-commandes', 'stats-commandes-pharmacie'],
            'Pharmacie' => ['gérer-pharmacies', 'gérer-rôles-pharmacies'],
            'Médicaments' => ['gérer-catalogue', 'stats-médicaments'],
            'Clients' => ['gérer-clients', 'gérer-doublons', 'stats-clients'],
            'Utilisateurs backoffice' => ['gérer-rôles-backoffice', 'visualiser-donnees', 'gérer-tout'],
            'Agent' => ['reception-commande', 'recherche-prix-pharmacie', 'transmission-livreur', 'renvoyer-pharmacie'],
            'Gérant' => ['créer-vendeurs', 'historique-pharmacie', 'confirmer-disponibilite'],
        ];
        $result = [];
        foreach ($groupes as $cat => $perms) {
            $items = [];
            foreach ($perms as $p) {
                if (isset(self::PERMISSION_LABELS[$p])) {
                    $items[] = ['name' => $p, 'label' => self::PERMISSION_LABELS[$p]];
                }
            }
            if (!empty($items)) {
                $result[$cat] = $items;
            }
        }
        return $result;
    }

    private function formatUser(User $user): array
    {
        $role = $user->roles->first();
        $allPermissions = $user->getAllPermissions()->pluck('name');
        $directPermissions = $user->getDirectPermissions()->pluck('name')->all();
        $rolePermissionNames = $role ? $role->permissions->pluck('name')->all() : [];
        $permissions = $allPermissions->map(fn ($n) => self::PERMISSION_LABELS[$n] ?? $n)->values()->all();
        $permissionNames = $allPermissions->values()->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'role' => $role ? [
                'name' => $role->name,
                'label' => $this->roleLabel($role->name),
                'permissions' => $role->permissions->map(fn ($p) => [
                    'name' => $p->name,
                    'label' => self::PERMISSION_LABELS[$p->name] ?? $p->name,
                ])->values()->all(),
            ] : null,
            'permissions' => $permissions,
            'permission_names' => $permissionNames,
            'direct_permissions' => $directPermissions,
            'role_permission_names' => $rolePermissionNames,
        ];
    }
}
