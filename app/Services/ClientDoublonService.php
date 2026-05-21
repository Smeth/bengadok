<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Commande;
use App\Models\GroupeDoublonsClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientDoublonService
{
    /**
     * Détecte les doublons selon plusieurs liaisons (nom, téléphone, adresse + nom/tél) et crée les groupes manquants.
     */
    public function detecterEtCreerGroupes(): void
    {
        $clients = Client::with(['zone', 'commandes' => fn ($q) => $q->whereIn('status', Commande::STATUTS_COMPTABILISES_CLIENT)])
            ->get();

        if ($clients->count() < 2) {
            return;
        }

        /** @var array<int, string> $normTel */
        $normTel = [];
        /** @var array<int, string> $normNom */
        $normNom = [];
        /** @var array<int, string> $normAdr */
        $normAdr = [];
        foreach ($clients as $c) {
            $normTel[(int) $c->id] = $this->normaliserTelephone((string) ($c->tel ?? ''));
            $normNom[(int) $c->id] = $this->normaliser($c->prenom.' '.$c->nom);
            $normAdr[(int) $c->id] = $this->normaliser((string) ($c->adresse ?? ''));
        }

        $uf = new ClientDoublonUnionFind($clients->pluck('id')->map(fn ($id) => (int) $id)->all());

        $telBuckets = [];
        foreach ($clients as $c) {
            $t = $normTel[(int) $c->id];
            if ($t === '' || strlen($t) < 8) {
                continue;
            }
            $telBuckets[$t][] = (int) $c->id;
        }
        foreach ($telBuckets as $ids) {
            $this->pairwiseUnion($uf, $ids);
        }

        $nomBuckets = [];
        foreach ($clients as $c) {
            $n = $normNom[(int) $c->id];
            if ($n === '') {
                continue;
            }
            $nomBuckets[$n][] = (int) $c->id;
        }
        foreach ($nomBuckets as $ids) {
            $this->pairwiseUnion($uf, $ids);
        }

        $adrBuckets = [];
        foreach ($clients as $c) {
            $a = $normAdr[(int) $c->id];
            if ($a === '') {
                continue;
            }
            $adrBuckets[$a][] = (int) $c->id;
        }
        foreach ($adrBuckets as $ids) {
            if (count($ids) < 2) {
                continue;
            }
            $nIds = count($ids);
            for ($i = 0; $i < $nIds; $i++) {
                for ($j = $i + 1; $j < $nIds; $j++) {
                    $ia = $ids[$i];
                    $ib = $ids[$j];
                    if ($normTel[$ia] !== '' && $normTel[$ia] === $normTel[$ib]) {
                        $uf->union($ia, $ib);

                        continue;
                    }
                    if ($normNom[$ia] !== '' && $normNom[$ia] === $normNom[$ib]) {
                        $uf->union($ia, $ib);

                        continue;
                    }
                }
            }
        }

        $components = [];
        foreach ($clients->pluck('id') as $rawId) {
            $cid = (int) $rawId;
            $root = $uf->find($cid);
            $components[$root][] = $cid;
        }

        foreach ($components as $idsDuGroupe) {
            $uniqueIds = collect($idsDuGroupe)->unique()->values()->all();
            if (count($uniqueIds) < 2) {
                continue;
            }

            $existant = GroupeDoublonsClient::whereHas('clients', fn ($q) => $q->whereIn('client_id', $uniqueIds))
                ->whereIn('statut', ['en_attente', 'verifie'])
                ->first();

            if ($existant) {
                continue;
            }

            $groupeClients = $clients->whereIn('id', $uniqueIds);
            $principalId = $this->choisirPrincipal($groupeClients);
            $criteres = $this->deduireCriteres($groupeClients, $normNom, $normTel, $normAdr);

            DB::transaction(function () use ($uniqueIds, $principalId, $criteres) {
                $groupe = GroupeDoublonsClient::create([
                    'statut' => 'en_attente',
                    'principal_client_id' => $principalId,
                    'criteres' => $criteres,
                ]);
                foreach ($uniqueIds as $clientId) {
                    $groupe->clients()->attach($clientId, ['is_principal' => $clientId === $principalId]);
                }
            });
        }
    }

    private function pairwiseUnion(ClientDoublonUnionFind $uf, array $ids): void
    {
        $ids = collect($ids)->unique()->filter()->values()->map(fn ($id) => (int) $id)->all();
        $n = count($ids);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $uf->union($ids[$i], $ids[$j]);
            }
        }
    }

    /**
     * @param  Collection<int, Client>  $groupeClients
     * @param  array<int, string>  $normNom
     * @param  array<int, string>  $normTel
     * @param  array<int, string>  $normAdr
     * @return list<string>
     */
    private function deduireCriteres(Collection $groupeClients, array $normNom, array $normTel, array $normAdr): array
    {
        $crit = [];

        $tels = $groupeClients->map(fn ($c) => $normTel[(int) $c->id])->filter(fn ($t) => $t !== '' && strlen($t) >= 8);
        if ($tels->isNotEmpty() && $tels->unique()->count() === 1) {
            $crit[] = 'tel_identique';
        }

        $noms = $groupeClients->map(fn ($c) => $normNom[(int) $c->id])->filter(fn ($n) => $n !== '');
        if ($noms->isNotEmpty() && $noms->unique()->count() === 1) {
            $crit[] = 'nom_identique';
        }

        if (in_array('tel_identique', $crit, true) && in_array('nom_identique', $crit, true)) {
            $crit[] = 'nom_et_tel_identique';
        }

        $adrs = $groupeClients->map(fn ($c) => $normAdr[(int) $c->id])->filter(fn ($a) => $a !== '');
        if ($adrs->isNotEmpty() && $adrs->unique()->count() === 1) {
            $crit[] = 'adresse_identique';
        }

        $zones = $groupeClients->pluck('zone_id')->unique()->filter();
        if ($zones->count() === 1 && $zones->first() !== null) {
            $crit[] = 'meme_zone';
        }

        return array_values(array_unique($crit));
    }

    /**
     * @param  iterable<Client>|Collection<int, Client>  $clients
     */
    private function choisirPrincipal(iterable $clients): int
    {
        $col = Collection::wrap($clients);
        $picked = $col->sortByDesc(fn ($c) => $c->commandes->filter(fn ($cmd) => in_array($cmd->status, Commande::STATUTS_COMPTABILISES_CLIENT, true))->count())
            ->sortByDesc(fn ($c) => $c->created_at?->timestamp ?? 0)
            ->first();

        if ($picked === null) {
            throw new \RuntimeException('Groupe doublons clients vide.');
        }

        return $picked->id;
    }

    private function normaliser(string $s): string
    {
        return trim(strtolower(preg_replace('/\s+/', ' ', $s)));
    }

    private function normaliserTelephone(string $tel): string
    {
        $digits = preg_replace('/\D+/', '', $tel);

        return is_string($digits) ? $digits : '';
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
            $payloadSecondaire = [];
            if ($ajouterTelSecondaire && $secondaires->isNotEmpty()) {
                $telSecondaire = $secondaires->first()->tel;
                if ($telSecondaire && $telSecondaire !== $principal->tel) {
                    $payloadSecondaire['tel_secondaire'] = $telSecondaire;
                }
            }

            if ($payloadSecondaire !== []) {
                $principal->update($payloadSecondaire);
            }

            foreach ($secondaires as $sec) {
                $sec->commandes()->update(['client_id' => $principal->id]);
                $sec->delete();
            }
            $groupe->update(['statut' => 'fusionne', 'principal_client_id' => $principal->id]);
        });
    }
}
