<?php

namespace App\Http\Controllers;

use App\Models\ClientFrequence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientFrequenceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $this->validated($request);
        $rawSlug = trim((string) ($validated['slug'] ?? ''));
        $base = $rawSlug !== '' ? Str::slug($rawSlug) : Str::slug($validated['designation']);
        $validated['slug'] = $this->uniqueSlug($base !== '' ? $base : 'frequence');
        $this->assertMinMaxCommandes($validated);

        ClientFrequence::query()->create($validated);

        return redirect()->route('parametres.index', ['onglet' => 'clientFrequences'])
            ->with('status', 'Fréquence créée.');
    }

    public function update(Request $request, ClientFrequence $clientFrequence): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $this->validated($request);
        $rawSlug = trim((string) ($validated['slug'] ?? ''));
        $base = $rawSlug !== '' ? Str::slug($rawSlug) : Str::slug($validated['designation']);
        $base = $base !== '' ? $base : $clientFrequence->slug;
        if ($base !== $clientFrequence->slug) {
            $validated['slug'] = $this->uniqueSlug($base, $clientFrequence->id);
        } else {
            $validated['slug'] = $clientFrequence->slug;
        }
        $this->assertMinMaxCommandes($validated);

        $clientFrequence->update($validated);

        return redirect()->route('parametres.index', ['onglet' => 'clientFrequences'])
            ->with('status', 'Fréquence mise à jour.');
    }

    public function destroy(Request $request, ClientFrequence $clientFrequence): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $clientFrequence->delete();

        return redirect()->route('parametres.index', ['onglet' => 'clientFrequences'])
            ->with('status', 'Fréquence supprimée.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertMinMaxCommandes(array $validated): void
    {
        $min = (int) ($validated['commandes_minimum'] ?? 0);
        $max = $validated['commandes_maximum'];
        if ($max !== null && (int) $max < $min) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'commandes_maximum' => 'Le nombre maximum de commandes doit être supérieur ou égal au minimum.',
            ]);
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'designation' => 'required|string|max:120',
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'commandes_minimum' => 'required|integer|min:0',
            'commandes_maximum' => 'nullable|integer|min:0',
            'intervalle_max_jours' => 'nullable|integer|min:1|max:3650',
            'priorite' => 'required|integer|min:0|max:255',
        ]);
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base !== '' ? $base : 'frequence';
        $candidate = $slug;
        $n = 0;
        while (ClientFrequence::query()
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $n++;
            $candidate = $slug.'-'.$n;
        }

        return $candidate;
    }

    private function authorizeAdmin(Request $request): void
    {
        if (! $request->user()?->hasAnyRole(['admin', 'super_admin'])) {
            abort(403);
        }
    }
}
