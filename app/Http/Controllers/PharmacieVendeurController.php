<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PharmacieUsernameGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PharmacieVendeurController extends Controller
{
    public function index(Request $request): Response
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (! $pharmacieId || ! $request->user()?->hasRole('gerant')) {
            abort(403);
        }

        $pharmacie = $request->user()->pharmacie;
        $vendeurs = User::where('pharmacie_id', $pharmacieId)
            ->role('vendeur')
            ->get();

        return Inertia::render('Pharmacie/Vendeurs', [
            'vendeurs' => $vendeurs,
            'pharmacie' => $pharmacie,
            'nextUserId' => PharmacieUsernameGenerator::predictNextUserId(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $pharmacieId = $user?->pharmacie_id;
        if (! $pharmacieId || ! $user?->hasRole('gerant')) {
            abort(403);
        }

        $pharmacie = $user->pharmacie;

        $request->merge([
            'email' => $request->filled('email') ? trim((string) $request->input('email')) : null,
            'phone' => trim((string) $request->input('phone', '')),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:32', Rule::unique('users', 'phone')],
            'password' => ['required', Password::defaults()],
            'role' => 'required|in:gerant,vendeur',
        ]);

        $tempPassword = $validated['password'];
        $email = $validated['email'] ?? null;
        $role = $validated['role'];

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'phone' => $validated['phone'],
            'username' => null,
            'password' => Hash::make($tempPassword),
            'pharmacie_id' => $pharmacieId,
            'email_verified_at' => $email ? now() : null,
        ]);
        $newUser->assignRole($role);

        $username = PharmacieUsernameGenerator::assign(
            $newUser,
            $pharmacie->designation ?? 'pharmacie',
            $role,
            $validated['name'],
        );

        return back()
            ->with('status', "Utilisateur {$newUser->name} créé.")
            ->with('createdUsername', $username)
            ->with('createdPassword', $tempPassword);
    }
}
