<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PharmacieVendeurController extends Controller
{
    public function index(Request $request): Response
    {
        $pharmacieId = $request->user()?->pharmacie_id;
        if (!$pharmacieId || !$request->user()?->hasRole('gerant')) {
            abort(403);
        }

        $pharmacie = $request->user()->pharmacie;
        $vendeurs = User::where('pharmacie_id', $pharmacieId)
            ->role('vendeur')
            ->get();

        $nextUserId = (User::max('id') ?? 0) + 1;

        return Inertia::render('Pharmacie/Vendeurs', [
            'vendeurs' => $vendeurs,
            'pharmacie' => $pharmacie,
            'nextUserId' => $nextUserId,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $pharmacieId = $user?->pharmacie_id;
        if (!$pharmacieId || !$user?->hasRole('gerant')) {
            abort(403);
        }

        $pharmacie = $user->pharmacie;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::defaults()],
        ]);

        $tempPassword = $validated['password'];

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => null,
            'password' => Hash::make($tempPassword),
            'pharmacie_id' => $pharmacieId,
            'email_verified_at' => now(),
        ]);
        $newUser->assignRole('vendeur');

        $slugPharma = Str::slug($pharmacie->designation ?? 'pharmacie');
        $slugNom = Str::slug($validated['name']);
        $username = "{$slugPharma}_vendeur_{$slugNom}_{$newUser->id}";
        $newUser->update(['username' => $username]);

        return back()->with('status', "Vendeur {$newUser->name} créé. Identifiant : {$username}");
    }
}
