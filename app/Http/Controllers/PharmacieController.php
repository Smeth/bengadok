<?php

namespace App\Http\Controllers;

use App\Models\Heur;
use App\Models\Pharmacie;
use App\Models\TypePharmacie;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PharmacieController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search', '');
        $query = Pharmacie::with(['zone', 'typePharmacie', 'heurs', 'users'])
            ->when($search, fn ($q) => $q->where('designation', 'like', "%{$search}%")
                ->orWhere('adresse', 'like', "%{$search}%")
                ->orWhere('telephone', 'like', "%{$search}%"))
            ->orderByDesc('created_at');

        $pharmacies = $query->paginate(8)->withQueryString()->through(function ($p) {
            $lat = $p->latitude ?? ($p->zone?->latitude ? (float) $p->zone->latitude + ($p->id * 0.001) : -4.2694 + ($p->id % 6) * 0.01);
            $lng = $p->longitude ?? ($p->zone?->longitude ? (float) $p->zone->longitude + ($p->id * 0.0005) : 15.2712 + ($p->id % 6) * 0.01);

            return [
                'id' => $p->id,
                'designation' => $p->designation,
                'adresse' => $p->adresse,
                'telephone' => $p->telephone,
                'email' => $p->email,
                'latitude' => $lat,
                'longitude' => $lng,
                'de_garde' => $p->de_garde,
                'proprio_nom' => $p->proprio_nom,
                'proprio_tel' => $p->proprio_tel,
                'zone' => $p->zone,
                'type_pharmacie' => $p->typePharmacie,
                'heurs' => $p->heurs,
                'users_count' => $p->users()->count(),
            ];
        });

        $nbDeGarde = Pharmacie::where('de_garde', true)->count();
        $nbTotal = Pharmacie::count();

        $pharmaciesForMap = Pharmacie::with(['typePharmacie', 'zone'])
            ->when($search, fn ($q) => $q->where('designation', 'like', "%{$search}%")
                ->orWhere('adresse', 'like', "%{$search}%")
                ->orWhere('telephone', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($p) {
                $lat = $p->latitude ?? ($p->zone?->latitude ? (float) $p->zone->latitude + ($p->id * 0.001) : -4.2694 + ($p->id % 6) * 0.01);
                $lng = $p->longitude ?? ($p->zone?->longitude ? (float) $p->zone->longitude + ($p->id * 0.0005) : 15.2712 + ($p->id % 6) * 0.01);

                return [
                    'id' => $p->id,
                    'designation' => $p->designation,
                    'adresse' => $p->adresse,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'de_garde' => $p->de_garde,
                    'type_pharmacie' => $p->typePharmacie,
                ];
            });

        return Inertia::render('Pharmacies/Index', [
            'pharmacies' => $pharmacies,
            'pharmaciesForMap' => $pharmaciesForMap,
            'filters' => ['search' => $search],
            'stats' => [
                'de_garde' => $nbDeGarde,
                'total' => $nbTotal,
            ],
            'zones' => Zone::orderBy('designation')->get(),
            'types' => TypePharmacie::with('heurs')->get(),
        ]);
    }

    public function show(Pharmacie $pharmacie): Response
    {
        $pharmacie->load(['zone', 'typePharmacie', 'heurs', 'users.roles']);

        $nextUserId = (User::max('id') ?? 0) + 1;

        return Inertia::render('Pharmacies/Show', [
            'nextUserId' => $nextUserId,
            'pharmacie' => [
                'id' => $pharmacie->id,
                'designation' => $pharmacie->designation,
                'adresse' => $pharmacie->adresse,
                'telephone' => $pharmacie->telephone,
                'email' => $pharmacie->email,
                'de_garde' => $pharmacie->de_garde,
                'proprio_nom' => $pharmacie->proprio_nom,
                'proprio_tel' => $pharmacie->proprio_tel,
                'proprio_email' => $pharmacie->proprio_email,
                'zone_id' => $pharmacie->zone_id,
                'zone' => $pharmacie->zone,
                'type_pharmacie_id' => $pharmacie->type_pharmacie_id,
                'type_pharmacie' => $pharmacie->typePharmacie,
                'heurs' => $pharmacie->heurs,
                'heure_ouverture' => $pharmacie->heurs?->ouverture ?? '08:00',
                'heure_fermeture' => $pharmacie->heurs?->fermeture ?? '19:00',
                'users' => $pharmacie->users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'username' => $u->username ?? $u->email,
                    'role' => $u->getRoleNames()->first() ?? 'Utilisateur',
                ]),
            ],
            'zones' => Zone::orderBy('designation')->get(),
            'types' => TypePharmacie::with('heurs')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:200',
            'adresse' => 'required|string',
            'telephone' => 'required|string|max:20',
            'email' => 'nullable|email',
            'zone_id' => 'nullable|exists:zones,id',
            'type_pharmacie_id' => 'required|exists:type_pharmacies,id',
            'heure_ouverture' => 'required|string',
            'heure_fermeture' => 'required|string',
            'proprio_nom' => 'required|string|max:100',
            'proprio_email' => 'nullable|email|unique:users,email',
            'proprio_tel' => 'nullable|string|max:20',
        ]);

        $heur = Heur::firstOrCreate(
            ['ouverture' => $validated['heure_ouverture'], 'fermeture' => $validated['heure_fermeture']]
        );

        $zoneId = $validated['zone_id'] ?? null;
        if (! $zoneId) {
            $zoneId = Zone::first()?->id;
        }

        $pharmacie = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $heur, $zoneId) {
            $pharmacie = Pharmacie::create([
                'zone_id' => $zoneId,
                'type_pharmacie_id' => $validated['type_pharmacie_id'],
                'heurs_id' => $heur->id,
                'designation' => $validated['designation'],
                'adresse' => $validated['adresse'],
                'telephone' => $validated['telephone'],
                'email' => $validated['email'] ?? null,
                'proprio_nom' => $validated['proprio_nom'],
                'proprio_tel' => $validated['proprio_tel'] ?? null,
                'proprio_email' => $validated['proprio_email'] ?? null,
            ]);

            $proprioEmail = $validated['proprio_email'] ?? null;
            if ($proprioEmail) {
                $tempPassword = Str::random(10);
                $gerant = User::create([
                    'name' => $validated['proprio_nom'],
                    'email' => $proprioEmail,
                    'username' => null,
                    'password' => Hash::make($tempPassword),
                    'pharmacie_id' => $pharmacie->id,
                    'email_verified_at' => now(),
                ]);
                $gerant->assignRole('gerant');

                $slugPharma = Str::slug($validated['designation']);
                $slugNom = Str::slug($validated['proprio_nom']);
                $gerant->update([
                    'username' => "{$slugPharma}_gerant_{$slugNom}_{$gerant->id}",
                ]);
            }

            return $pharmacie;
        });

        $message = $pharmacie->users()->exists()
            ? "Pharmacie créée avec succès. Compte gérant créé (identifiant : {$pharmacie->users()->first()?->username})."
            : 'Pharmacie créée avec succès. Vous pourrez ajouter un gérant ultérieurement.';

        return redirect()->route('pharmacies.index')->with('status', $message);
    }

    public function update(Request $request, Pharmacie $pharmacie): RedirectResponse
    {
        $validated = $request->validate([
            'designation' => 'required|string|max:200',
            'adresse' => 'required|string',
            'telephone' => 'required|string|max:20',
            'email' => 'nullable|email',
            'zone_id' => 'nullable|exists:zones,id',
            'type_pharmacie_id' => 'required|exists:type_pharmacies,id',
            'heure_ouverture' => 'required|string',
            'heure_fermeture' => 'required|string',
            'proprio_nom' => 'required|string|max:100',
            'proprio_email' => 'nullable|email',
            'proprio_tel' => 'nullable|string|max:20',
        ]);

        $heur = Heur::firstOrCreate(
            ['ouverture' => $validated['heure_ouverture'], 'fermeture' => $validated['heure_fermeture']]
        );

        $pharmacie->update([
            'zone_id' => $validated['zone_id'] ?? null,
            'type_pharmacie_id' => $validated['type_pharmacie_id'],
            'heurs_id' => $heur->id,
            'designation' => $validated['designation'],
            'adresse' => $validated['adresse'],
            'telephone' => $validated['telephone'],
            'email' => $validated['email'] ?? null,
            'proprio_nom' => $validated['proprio_nom'],
            'proprio_tel' => $validated['proprio_tel'] ?? null,
            'proprio_email' => $validated['proprio_email'] ?? null,
        ]);

        return back()->with('status', 'Pharmacie mise à jour.');
    }

    public function toggleGarde(Pharmacie $pharmacie): RedirectResponse
    {
        $pharmacie->update(['de_garde' => ! $pharmacie->de_garde]);
        $message = $pharmacie->de_garde ? 'Pharmacie mise de garde.' : 'Garde retirée.';

        return back()->with('status', $message);
    }

    public function destroy(Pharmacie $pharmacie): RedirectResponse
    {
        $pharmacie->delete();

        return redirect()->route('pharmacies.index')->with('status', 'Pharmacie supprimée.');
    }

    public function storeUser(Request $request, Pharmacie $pharmacie): RedirectResponse
    {
        if (! $request->user()?->hasAnyRole(['admin', 'super_admin'])) {
            abort(403, 'Seuls les admins peuvent ajouter des utilisateurs à une pharmacie.');
        }

        $request->merge([
            'email' => $request->filled('email') ? trim((string) $request->input('email')) : null,
        ]);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:32', Rule::unique('users', 'phone')],
            'role' => 'required|in:gerant,vendeur',
        ];
        $rules['password'] = ['nullable', Password::defaults()];

        $validated = $request->validate($rules);

        $password = ! empty($validated['password'])
            ? $validated['password']
            : Str::random(10);

        $email = $validated['email'] ?? null;

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'phone' => $validated['phone'],
            'username' => null,
            'password' => Hash::make($password),
            'pharmacie_id' => $pharmacie->id,
            'email_verified_at' => $email ? now() : null,
        ]);
        $newUser->assignRole($validated['role']);

        $slugPharma = Str::slug($pharmacie->designation ?? 'pharmacie');
        $slugNom = Str::slug($validated['name']);
        $username = "{$slugPharma}_{$validated['role']}_{$slugNom}_{$newUser->id}";
        $newUser->update(['username' => $username]);

        return back()
            ->with('status', "Utilisateur {$newUser->name} créé.")
            ->with('createdUsername', $username);
    }

    public function resetUserPassword(Request $request, Pharmacie $pharmacie, User $user): RedirectResponse
    {
        if (! $request->user()?->hasAnyRole(['admin', 'super_admin'])) {
            abort(403, 'Seuls les admins peuvent réinitialiser les mots de passe.');
        }
        if ($user->pharmacie_id !== $pharmacie->id) {
            abort(403, "Cet utilisateur n'appartient pas à cette pharmacie.");
        }

        $newPassword = Str::random(10);
        $user->update(['password' => Hash::make($newPassword)]);

        return back()->with('status', "Mot de passe réinitialisé pour {$user->name}. Nouveau mot de passe : {$newPassword}");
    }

    public function destroyUser(Request $request, Pharmacie $pharmacie, User $user): RedirectResponse
    {
        if (! $request->user()?->hasAnyRole(['admin', 'super_admin'])) {
            abort(403, 'Seuls les admins peuvent supprimer des utilisateurs.');
        }
        if ($user->pharmacie_id !== $pharmacie->id) {
            abort(403, "Cet utilisateur n'appartient pas à cette pharmacie.");
        }

        if ($request->user()->id === $user->id) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return back()->with('error', 'Impossible de supprimer un compte administrateur depuis cette page.');
        }

        $label = $user->name;

        DB::transaction(function () use ($user): void {
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->purgeAuthenticationFootprint();
            $user->delete();
        });

        return back()->with('status', "Utilisateur « {$label} » supprimé définitivement.");
    }
}
