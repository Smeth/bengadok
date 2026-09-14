<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Heur;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\MontantLivraison;
use App\Models\Pharmacie;
use App\Models\TypePharmacie;
use App\Models\Zone;
use App\Support\ClientPayloadNormalizer;
use RuntimeException;

/**
 * Résolution / création d'entités BengaDok pour l'intégration des commandes historiques.
 */
class CommandeEntityResolverService
{
    /**
     * @return array{nom: ?string, prenom: ?string, sexe: ?string}
     */
    public function parseClientName(?string $fullName): array
    {
        $text = trim((string) $fullName);
        if ($text === '') {
            return ['nom' => null, 'prenom' => null, 'sexe' => null];
        }

        $sexe = null;
        if (preg_match('/^(madame|mme|mlle|mademoiselle)\.?\s+/iu', $text, $m)) {
            $sexe = 'F';
            $text = trim(substr($text, strlen($m[0])));
        } elseif (preg_match('/^(mr|monsieur|m\.)\.?\s+/iu', $text, $m)) {
            $sexe = 'M';
            $text = trim(substr($text, strlen($m[0])));
        }

        $parts = preg_split('/\s+/u', $text) ?: [];
        if (count($parts) >= 2) {
            return [
                'prenom' => $parts[0],
                'nom' => implode(' ', array_slice($parts, 1)),
                'sexe' => $sexe,
            ];
        }

        return [
            'nom' => $text,
            'prenom' => null,
            'sexe' => $sexe,
        ];
    }

    public function normalizeTelDigits(?string $tel): string
    {
        return (string) preg_replace('/\D+/', '', (string) $tel);
    }

    public function findClientByTel(?string $tel): ?Client
    {
        $digits = $this->normalizeTelDigits($tel);
        if ($digits === '') {
            return null;
        }

        $suffix = substr($digits, -8);

        return Client::query()
            ->whereNotNull('tel')
            ->where('tel', '<>', '')
            ->get()
            ->first(function (Client $client) use ($suffix) {
                $clientDigits = $this->normalizeTelDigits($client->tel);
                if ($clientDigits === '') {
                    return false;
                }

                return str_ends_with($clientDigits, $suffix)
                    || str_ends_with($suffix, substr($clientDigits, -8));
            });
    }

    public function normalizeArrondissement(?string $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        foreach (Client::ARRONDISSEMENTS as $arrondissement) {
            if (mb_strtolower($arrondissement) === mb_strtolower($text)) {
                return $arrondissement;
            }
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $normalized = mb_strtolower((string) ($ascii !== false ? $ascii : $text));

        foreach (Client::ARRONDISSEMENTS as $arrondissement) {
            $candidate = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $arrondissement);
            $candidateNorm = mb_strtolower((string) ($candidate !== false ? $candidate : $arrondissement));
            if ($candidateNorm === $normalized || str_contains($normalized, $candidateNorm)) {
                return $arrondissement;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolveClient(array $data): Client
    {
        if (! empty($data['client_id'])) {
            $client = Client::query()->findOrFail((int) $data['client_id']);
            $attrs = $this->clientAttributesFromPayload($data, onlyPresentKeys: true);

            if ($attrs !== []) {
                $client->update($attrs);
            }

            return $client->fresh();
        }

        $existing = $this->findClientByTel($data['client_tel'] ?? null);
        if ($existing) {
            $attrs = $this->clientAttributesFromPayload($data, onlyPresentKeys: true);

            if ($attrs !== []) {
                $existing->update($attrs);
            }

            return $existing->fresh();
        }

        return Client::query()->create($this->clientAttributesFromPayload($data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function clientAttributesFromPayload(array $data, bool $onlyPresentKeys = false): array
    {
        $attrs = ClientPayloadNormalizer::attributesFromCommandePayload($data, $onlyPresentKeys);

        if ($onlyPresentKeys && ! array_key_exists('client_arrondissement', $data)) {
            return $attrs;
        }

        $attrs['arrondissement'] = $this->normalizeArrondissement($data['client_arrondissement'] ?? null);

        return $attrs;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function resolveClientFromLegacyRow(array $row): Client
    {
        $parsed = $this->parseClientName($row['nom_client'] ?? null);
        $sexe = $row['sexe'] ?? $parsed['sexe'];

        return $this->resolveClient([
            'client_nom' => $parsed['nom'],
            'client_prenom' => $parsed['prenom'],
            'client_tel' => $row['telephone'] ?? '',
            'client_adresse' => $row['adresse_livraison'] ?? '',
            'client_arrondissement' => $row['arrondissement'] ?? null,
            'client_sexe' => $sexe,
        ]);
    }

    public function resolvePharmacie(?int $pharmacieId, ?string $designation, ?string $arrondissement): Pharmacie
    {
        if ($pharmacieId) {
            return Pharmacie::query()->findOrFail($pharmacieId);
        }

        $name = trim((string) $designation);
        if ($name === '') {
            throw new RuntimeException('Pharmacie introuvable : aucune désignation fournie.');
        }

        $existing = $this->findPharmacieByName($name);
        if ($existing) {
            return $existing;
        }

        return $this->createMinimalPharmacie($name, $arrondissement, estPartenaire: false);
    }

    public function findPharmacieByName(string $name): ?Pharmacie
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            $name,
            str_starts_with(mb_strtolower($name), 'pharmacie ')
                ? $name
                : "Pharmacie {$name}",
            preg_replace('/^pharmacie\s+/iu', '', $name) ?: null,
        ])));

        foreach ($candidates as $candidate) {
            $exact = Pharmacie::query()->where('designation', $candidate)->first();
            if ($exact) {
                return $exact;
            }
        }

        $needle = preg_replace('/^pharmacie\s+/iu', '', $name) ?: $name;

        return Pharmacie::query()
            ->where('designation', 'like', '%'.$needle.'%')
            ->orderBy('id')
            ->first();
    }

    public function createMinimalPharmacie(
        string $designation,
        ?string $arrondissement,
        ?string $telephone = null,
        ?string $adresse = null,
        ?string $noteInterne = null,
        bool $estPartenaire = false,
    ): Pharmacie {
        $designation = trim($designation);
        if (! str_starts_with(mb_strtolower($designation), 'pharmacie ')) {
            $designation = 'Pharmacie '.$designation;
        }

        $arrondissement = $this->normalizeArrondissement($arrondissement);
        $zone = $arrondissement
            ? Zone::query()->where('designation', $arrondissement)->first()
            : null;
        $zone ??= Zone::query()->orderBy('id')->first();

        if (! $zone) {
            throw new RuntimeException('Impossible de créer la pharmacie : aucune zone disponible.');
        }

        $type = TypePharmacie::query()->orderBy('id')->first();
        $heur = Heur::query()->orderBy('id')->first();

        if (! $type || ! $heur) {
            throw new RuntimeException('Impossible de créer la pharmacie : référentiels manquants.');
        }

        $telephoneNormalized = trim((string) ($telephone ?? ''));
        $adresseNormalized = trim((string) ($adresse ?? ''));

        $pharmacie = Pharmacie::query()->create([
            'zone_id' => $zone->id,
            'type_pharmacie_id' => $type->id,
            'heurs_id' => $heur->id,
            'designation' => $designation,
            'est_partenaire' => $estPartenaire,
            'credits_actif' => false,
            'adresse' => $adresseNormalized !== ''
                ? $adresseNormalized
                : ($arrondissement
                    ? "Adresse à compléter — {$arrondissement}"
                    : 'Adresse à compléter'),
            'telephone' => $telephoneNormalized !== '' ? $telephoneNormalized : '000000000',
            'proprio_nom' => 'À compléter',
            'note_interne' => $noteInterne
                ?? ($estPartenaire
                    ? 'Créée automatiquement.'
                    : 'Pharmacie non partenaire — créée automatiquement (nom seul).'),
        ]);

        CommandeReferentielsService::invalidateCache();

        return $pharmacie;
    }

    public function resolveModePaiementId(?int $modePaiementId, ?string $label): ?int
    {
        if ($modePaiementId) {
            return $modePaiementId;
        }

        $label = trim((string) $label);
        if ($label === '') {
            return null;
        }

        $mode = ModePaiement::query()
            ->where('designation', 'like', '%'.$label.'%')
            ->orderBy('id')
            ->first();

        return $mode?->id;
    }

    public function resolveMontantLivraisonId(?int $montantLivraisonId, ?float $amount): ?int
    {
        if ($montantLivraisonId) {
            return $montantLivraisonId;
        }

        if ($amount === null) {
            return null;
        }

        $amount = (int) round($amount);
        $existing = MontantLivraison::query()->where('designation', $amount)->first();
        if ($existing) {
            return $existing->id;
        }

        return MontantLivraison::query()->create(['designation' => $amount])->id;
    }

    public function resolveLivreurId(?int $livreurId, ?string $name): ?int
    {
        if ($livreurId) {
            return $livreurId;
        }

        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $prenom = $parts[0] ?? $name;
        $nom = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $name;

        $existing = Livreur::query()
            ->where(function ($q) use ($name, $prenom, $nom) {
                $q->whereRaw("CONCAT(COALESCE(prenom,''), ' ', COALESCE(nom,'')) like ?", ['%'.$name.'%'])
                    ->orWhere('prenom', 'like', '%'.$prenom.'%')
                    ->orWhere('nom', 'like', '%'.$nom.'%');
            })
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return Livreur::query()->create([
            'prenom' => $prenom,
            'nom' => $nom,
            'tel' => '',
        ])->id;
    }

    /**
     * @return list<array{designation: string, dosage: ?string, forme: ?string, quantite: int, prix_unitaire: float, type: ?string}>
     */
    public function parseProduitLinesFromLegacyRow(array $row): array
    {
        $raw = trim((string) ($row['medicaments'] ?? ''));
        if ($raw === '') {
            $raw = 'Article non détaillé';
        }

        $raw = preg_replace('/\(\s*\d[\d\s.,]*\s*F\s*\)/iu', '', $raw) ?? $raw;
        $raw = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);

        $defaultQty = max(1, (int) ($row['quantite'] ?? 1));
        $montant = (float) ($row['montant_produits'] ?? 0);
        $caMed = (float) ($row['ca_medicaments'] ?? 0);
        $caPara = (float) ($row['ca_parapharmacie'] ?? 0);

        if ($caPara > 0 && $caMed > 0) {
            return [
                [
                    'designation' => $raw,
                    'dosage' => null,
                    'forme' => null,
                    'quantite' => $defaultQty,
                    'prix_unitaire' => round($caMed / $defaultQty, 2),
                    'type' => null,
                ],
                [
                    'designation' => $raw,
                    'dosage' => null,
                    'forme' => null,
                    'quantite' => 1,
                    'prix_unitaire' => $caPara,
                    'type' => 'Parapharmacie',
                ],
            ];
        }

        $type = ($caPara > 0 && $caMed <= 0) ? 'Parapharmacie' : null;
        $segments = $this->splitLegacyMedicamentSegments($raw);

        if (count($segments) <= 1) {
            $unit = $defaultQty > 0 ? round($montant / $defaultQty, 2) : $montant;

            return [[
                'designation' => $segments[0]['designation'],
                'dosage' => null,
                'forme' => null,
                'quantite' => $segments[0]['quantite'] ?? $defaultQty,
                'prix_unitaire' => $unit,
                'type' => $type,
            ]];
        }

        $perLine = count($segments) > 0 ? round($montant / count($segments), 2) : $montant;

        return array_map(static function (array $segment) use ($perLine, $type) {
            $qty = max(1, (int) ($segment['quantite'] ?? 1));

            return [
                'designation' => $segment['designation'],
                'dosage' => null,
                'forme' => null,
                'quantite' => $qty,
                'prix_unitaire' => round($perLine / $qty, 2),
                'type' => $type,
            ];
        }, $segments);
    }

    /**
     * @return list<array{designation: string, quantite?: int}>
     */
    private function splitLegacyMedicamentSegments(string $raw): array
    {
        $parts = preg_split('/\s*(?:\+|;|\bet\b)\s*/iu', $raw) ?: [$raw];
        $segments = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $qty = 1;
            if (preg_match('/\bx\s*(\d+)\s*$/iu', $part, $m)) {
                $qty = max(1, (int) $m[1]);
                $part = trim(preg_replace('/\bx\s*\d+\s*$/iu', '', $part) ?? $part);
            } elseif (preg_match('/\b(?:boite|boîte|flacon|tube)\s*x\s*(\d+)\b/iu', $part, $m)) {
                $qty = max(1, (int) $m[1]);
            }

            if ($part !== '') {
                $segments[] = ['designation' => $part, 'quantite' => $qty];
            }
        }

        return $segments !== [] ? $segments : [['designation' => $raw, 'quantite' => 1]];
    }
}
