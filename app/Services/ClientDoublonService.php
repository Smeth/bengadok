<?php

namespace App\Services;

use App\Models\Client;
use App\Models\GroupeDoublonsClient;
use Illuminate\Support\Facades\DB;

class ClientDoublonService
{
    /**
     * Détecte les doublons (même nom complet) et crée les groupes manquants.
     */
    public function detecterEtCreerGroupes(): void
    {
        $clients = Client::with(['zone', 'commandes' => fn ($q) => $q->whereIn('status', ['validee', 'livree', 'a_preparer'])])
            ->get();

        $groupesParNom = $clients->groupBy(fn ($c) => $this->normaliser($c->prenom . ' ' . $c->nom));

        foreach ($groupesParNom as $nomNorm => $groupeClients) {
            if ($nomNorm === '' || $groupeClients->count() < 2) {
                continue;
            }

            $ids = $groupeClients->pluck('id')->values();

            $existant = GroupeDoublonsClient::whereHas('clients', fn ($q) => $q->whereIn('client_id', $ids))
                ->whereIn('statut', ['en_attente', 'verifie'])
                ->first();

            if ($existant) {
                continue;
            }

            $principalId = $this->choisirPrincipal($groupeClients);
            $criteres = ['nom_identique'];
            $adresses = $groupeClients->map(fn ($c) => $this->normaliser($c->adresse ?? ''))->unique()->filter();
            $zones = $groupeClients->pluck('zone_id')->unique()->filter();
            if ($adresses->count() === 1 && $adresses->first() !== '') {
                $criteres[] = 'adresse_identique';
            }
            if ($zones->count() === 1) {
                $criteres[] = 'meme_zone';
            }

            DB::transaction(function () use ($ids, $principalId, $criteres) {
                $groupe = GroupeDoublonsClient::create([
                    'statut' => 'en_attente',
                    'principal_client_id' => $principalId,
                    'criteres' => $criteres,
                ]);
                foreach ($ids as $clientId) {
                    $groupe->clients()->attach($clientId, ['is_principal' => $clientId === $principalId]);
                }
            });
        }
    }

    private function normaliser(string $s): string
    {
        return trim(strtolower(preg_replace('/\s+/', ' ', $s)));
    }

    private function choisirPrincipal($clients)
    {
        return $clients->sortByDesc(fn ($c) => $c->commandes->filter(fn ($cmd) => in_array($cmd->status, ['validee', 'livree', 'a_preparer']))->count())
            ->sortByDesc(fn ($c) => $c->created_at?->timestamp ?? 0)
            ->first()->id;
    }

    /**
     * Fusionne les profils: garde le principal, transfère les commandes, supprime les autres.
     * Si $ajouterTelSecondaire, le numéro du premier doublon est ajouté comme tel_secondaire sur le principal.
     */
    public function fusionner(GroupeDoublonsClient $groupe, bool $ajouterTelSecondaire = false): void
    {
        $groupe->load('clients');
        $principal = $groupe->principalClient ?? $groupe->clients->first();
        $secondaires = $groupe->clients->filter(fn ($c) => $c->id !== $principal->id);

        DB::transaction(function () use ($principal, $secondaires, $groupe, $ajouterTelSecondaire) {
            if ($ajouterTelSecondaire && $secondaires->isNotEmpty()) {
                $telSecondaire = $secondaires->first()->tel;
                if ($telSecondaire && $telSecondaire !== $principal->tel) {
                    $principal->update(['tel_secondaire' => $telSecondaire]);
                }
            }
            foreach ($secondaires as $sec) {
                $sec->commandes()->update(['client_id' => $principal->id]);
                $sec->delete();
            }
            $groupe->update(['statut' => 'fusionne', 'principal_client_id' => $principal->id]);
        });
    }
}
