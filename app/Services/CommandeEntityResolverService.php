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

/** @phpstan-type LegacyProduitSegment array{designation: string, quantite: int, prix_total: ?float} */
/** @phpstan-type LegacyProduitLine array{designation: string, dosage: ?string, forme: ?string, quantite: int, prix_unitaire: float, type: ?string} */

/**
 * Résolution / création d'entités BengaDok pour l'intégration des commandes historiques.
 */
class CommandeEntityResolverService
{
    /** @var array<string, Client>|null */
    private ?array $clientsByTelSuffix = null;

    public function beginBulkClientLookup(): void
    {
        $this->clientsByTelSuffix = [];

        foreach (Client::query()->whereNotNull('tel')->where('tel', '<>', '')->cursor() as $client) {
            $this->rememberClient($client);
        }
    }

    public function endBulkClientLookup(): void
    {
        $this->clientsByTelSuffix = null;
    }

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

        if ($this->clientsByTelSuffix !== null) {
            if (isset($this->clientsByTelSuffix[$suffix])) {
                return $this->clientsByTelSuffix[$suffix];
            }

            foreach ($this->clientsByTelSuffix as $client) {
                if ($this->clientMatchesTelSuffix($client, $suffix)) {
                    return $client;
                }
            }

            return null;
        }

        return Client::query()
            ->whereNotNull('tel')
            ->where('tel', '<>', '')
            ->get()
            ->first(fn (Client $client) => $this->clientMatchesTelSuffix($client, $suffix));
    }

    private function clientMatchesTelSuffix(Client $client, string $suffix): bool
    {
        $clientDigits = $this->normalizeTelDigits($client->tel);
        if ($clientDigits === '') {
            return false;
        }

        return str_ends_with($clientDigits, $suffix)
            || str_ends_with($suffix, substr($clientDigits, -8));
    }

    private function rememberClient(Client $client): void
    {
        if ($this->clientsByTelSuffix === null) {
            return;
        }

        $digits = $this->normalizeTelDigits($client->tel);
        if ($digits === '') {
            return;
        }

        $this->clientsByTelSuffix[substr($digits, -8)] = $client;
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

            $fresh = $existing->fresh();
            $this->rememberClient($fresh);

            return $fresh;
        }

        $created = Client::query()->create($this->clientAttributesFromPayload($data));
        $this->rememberClient($created);

        return $created;
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
     * @return list<LegacyProduitLine>
     */
    public function parseProduitLinesFromLegacyRow(array $row): array
    {
        $raw = trim((string) ($row['medicaments'] ?? ''));
        if ($raw === '') {
            $raw = 'Article non détaillé';
        }

        $defaultQty = max(1, (int) ($row['quantite'] ?? 1));
        $caMed = max(0.0, (float) ($row['ca_medicaments'] ?? 0));
        $caPara = max(0.0, (float) ($row['ca_parapharmacie'] ?? 0));
        $montant = $this->resolveLegacyMontantProduits($row, $caMed, $caPara);

        $segments = $this->parseMedicamentSegmentsWithPrices($raw);
        if ($segments === []) {
            $segments = [['designation' => $raw, 'quantite' => $defaultQty, 'prix_total' => null]];
        }

        $lines = $this->buildLegacyProduitLinesFromSegments(
            $segments,
            $defaultQty,
            $montant,
            $caMed,
            $caPara,
        );

        return $this->reconcileLegacyProduitLines($lines, $montant, $caMed, $caPara);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveLegacyMontantProduits(array $row, float $caMed, float $caPara): float
    {
        $caSum = $caMed + $caPara;
        $montantRaw = $row['montant_produits'] ?? null;
        $montant = $montantRaw !== null && $montantRaw !== '' ? (float) $montantRaw : null;

        if ($montant !== null && $montant > 0) {
            if ($caSum > 0 && abs($montant - $caSum) > 1.0) {
                return $caSum;
            }

            return $montant;
        }

        return $caSum > 0 ? $caSum : 0.0;
    }

    /**
     * @return list<LegacyProduitSegment>
     */
    private function parseMedicamentSegmentsWithPrices(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $parts = preg_split('/\s*(?:\+|;|\bet\b|\n)\s*/iu', $raw) ?: [$raw];
        $segments = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $prixTotal = null;
            if (preg_match('/\(\s*([\d\s.,]+)\s*(?:f(?:cfa)?|xaf|cfa)?\s*\)/iu', $part, $priceMatch)) {
                $prixTotal = $this->parseLegacyAmountToken($priceMatch[1]);
                $part = trim(preg_replace('/\(\s*[\d\s.,]+\s*(?:f(?:cfa)?|xaf|cfa)?\s*\)/iu', '', $part) ?? $part);
            }

            $qty = 1;
            if (preg_match('/\bx\s*(\d+)\s*$/iu', $part, $m)) {
                $qty = max(1, (int) $m[1]);
                $part = trim(preg_replace('/\bx\s*\d+\s*$/iu', '', $part) ?? $part);
            } elseif (preg_match('/\b(?:boite|boîte|flacon|tube)\s*x\s*(\d+)\b/iu', $part, $m)) {
                $qty = max(1, (int) $m[1]);
            }

            if ($part === '') {
                continue;
            }

            $segments[] = [
                'designation' => $part,
                'quantite' => $qty,
                'prix_total' => $prixTotal,
            ];
        }

        return $segments;
    }

    private function parseLegacyAmountToken(string $token): ?float
    {
        $text = trim($token);
        $text = str_replace(["\u{00a0}", ' '], '', $text);
        $text = str_replace(',', '.', $text);
        $text = preg_replace('/[^0-9.\-]/', '', $text) ?? '';

        if ($text === '' || ! is_numeric($text)) {
            return null;
        }

        return (float) $text;
    }

    /**
     * @param  list<LegacyProduitSegment>  $segments
     * @return list<LegacyProduitLine>
     */
    private function buildLegacyProduitLinesFromSegments(
        array $segments,
        int $defaultQty,
        float $montant,
        float $caMed,
        float $caPara,
    ): array {
        $allHavePrices = $segments !== []
            && collect($segments)->every(fn (array $s) => ($s['prix_total'] ?? null) !== null && (float) $s['prix_total'] > 0);

        if ($allHavePrices) {
            return array_map(function (array $segment): array {
                $qty = max(1, (int) $segment['quantite']);
                $total = (float) $segment['prix_total'];

                return [
                    'designation' => $segment['designation'],
                    'dosage' => null,
                    'forme' => null,
                    'quantite' => $qty,
                    'prix_unitaire' => round($total / $qty, 2),
                    'type' => $this->guessLegacyProduitType($segment['designation']),
                ];
            }, $segments);
        }

        if (count($segments) === 1) {
            $segment = $segments[0];
            $qty = max(1, (int) ($segment['quantite'] ?? $defaultQty));
            $designation = $segment['designation'];

            if ($caMed > 0 && $caPara > 0) {
                return [
                    $this->legacyLine($designation, $qty, $caMed / $qty, null),
                    $this->legacyLine('Parapharmacie (import Excel)', 1, $caPara, 'Parapharmacie'),
                ];
            }

            $singleType = ($caPara > 0 && $caMed <= 0) ? 'Parapharmacie' : $this->guessLegacyProduitType($designation);
            $lineTotal = $montant > 0 ? $montant : ($caPara > 0 ? $caPara : $caMed);

            return [
                $this->legacyLine($designation, $qty, $qty > 0 ? $lineTotal / $qty : $lineTotal, $singleType),
            ];
        }

        $medSegments = [];
        $paraSegments = [];
        foreach ($segments as $segment) {
            if ($this->guessLegacyProduitType($segment['designation']) === 'Parapharmacie') {
                $paraSegments[] = $segment;
            } else {
                $medSegments[] = $segment;
            }
        }

        if ($caPara > 0 && $paraSegments === [] && $medSegments !== []) {
            $paraSegments[] = [
                'designation' => 'Parapharmacie (import Excel)',
                'quantite' => 1,
                'prix_total' => null,
            ];
        }

        $lines = [];

        if ($caMed > 0 || $caPara > 0) {
            $this->appendLegacyLinesForSegmentGroup($lines, $medSegments, $caMed > 0 ? $caMed : 0.0, null);
            $this->appendLegacyLinesForSegmentGroup($lines, $paraSegments, $caPara > 0 ? $caPara : 0.0, 'Parapharmacie');

            if ($lines !== []) {
                return $lines;
            }
        }

        $weightSum = 0;
        foreach ($segments as $segment) {
            $weightSum += max(1, (int) $segment['quantite']);
        }
        $weightSum = max(1, $weightSum);

        foreach ($segments as $segment) {
            $qty = max(1, (int) $segment['quantite']);
            $share = $montant > 0 ? ($montant * $qty / $weightSum) : 0.0;

            $lines[] = $this->legacyLine(
                $segment['designation'],
                $qty,
                $qty > 0 ? $share / $qty : $share,
                $this->guessLegacyProduitType($segment['designation']),
            );
        }

        return $lines;
    }

    /**
     * @param  list<LegacyProduitLine>  $lines
     * @param  list<LegacyProduitSegment>  $segments
     */
    private function appendLegacyLinesForSegmentGroup(
        array &$lines,
        array $segments,
        float $targetTotal,
        ?string $forcedType,
    ): void {
        if ($segments === [] || $targetTotal <= 0) {
            return;
        }

        $weightSum = 0;
        foreach ($segments as $segment) {
            $weightSum += max(1, (int) $segment['quantite']);
        }
        $weightSum = max(1, $weightSum);

        foreach ($segments as $segment) {
            $qty = max(1, (int) $segment['quantite']);
            $share = $targetTotal * $qty / $weightSum;

            $lines[] = $this->legacyLine(
                $segment['designation'],
                $qty,
                $qty > 0 ? $share / $qty : $share,
                $forcedType ?? $this->guessLegacyProduitType($segment['designation']),
            );
        }
    }

    /**
     * @return LegacyProduitLine
     */
    private function legacyLine(string $designation, int $quantite, float $prixUnitaire, ?string $type): array
    {
        return [
            'designation' => $designation,
            'dosage' => null,
            'forme' => null,
            'quantite' => max(1, $quantite),
            'prix_unitaire' => round(max(0, $prixUnitaire), 2),
            'type' => $type,
        ];
    }

    private function guessLegacyProduitType(string $designation): ?string
    {
        $lower = mb_strtolower(trim($designation), 'UTF-8');
        if ($lower === '') {
            return null;
        }

        if (str_contains($lower, 'parapharm')) {
            return 'Parapharmacie';
        }

        foreach ([
            'vitamine', 'complément', 'complement', 'shampoo', 'shampoing', 'gel douche',
            'crème', 'creme', 'lotion', 'savon', 'exfoliant', 'oxiprolane', 'dentifrice',
            'brosse', 'pansement', 'sérum', 'serum', 'huile', 'spray', 'déodorant', 'deodorant',
        ] as $keyword) {
            if (str_contains($lower, $keyword)) {
                return 'Parapharmacie';
            }
        }

        return null;
    }

    /**
     * @param  list<LegacyProduitLine>  $lines
     * @return list<LegacyProduitLine>
     */
    private function reconcileLegacyProduitLines(
        array $lines,
        float $montant,
        float $caMed,
        float $caPara,
    ): array {
        if ($lines === []) {
            return [$this->legacyLine('Article non détaillé', 1, max(0, $montant), null)];
        }

        if ($caMed > 0 || $caPara > 0) {
            $this->scaleLegacyLineGroup($lines, false, $caMed);
            $this->scaleLegacyLineGroup($lines, true, $caPara);
        } elseif ($montant > 0) {
            $this->scaleLegacyLineGroup($lines, null, $montant);
        }

        return $lines;
    }

    /**
     * @param  list<LegacyProduitLine>  $lines
     */
    private function scaleLegacyLineGroup(array &$lines, ?bool $parapharmaOnly, float $target): void
    {
        if ($target <= 0) {
            return;
        }

        $indexes = [];
        $current = 0.0;

        foreach ($lines as $index => $line) {
            $isPara = CommandeMontantCalculator::isParapharmaType($line['type'] ?? null);
            if ($parapharmaOnly === null || $isPara === $parapharmaOnly) {
                $indexes[] = $index;
                $current += (float) $line['prix_unitaire'] * (int) $line['quantite'];
            }
        }

        if ($indexes === []) {
            return;
        }

        if ($current <= 0) {
            $first = $indexes[0];
            $qty = max(1, (int) $lines[$first]['quantite']);
            $lines[$first]['prix_unitaire'] = round($target / $qty, 2);

            return;
        }

        $factor = $target / $current;
        foreach ($indexes as $index) {
            $lines[$index]['prix_unitaire'] = round((float) $lines[$index]['prix_unitaire'] * $factor, 2);
        }
    }
}
