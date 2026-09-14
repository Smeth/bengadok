<?php

namespace App\Services;

use App\Models\DbCommande;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class DbCommandeImportService
{
    /**
     * @return array{imported: int, skipped: int, errors: list<string>, total: int}
     */
    public function importUploadedFile(UploadedFile $file, bool $skipDuplicates = true): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        $contents = file_get_contents($file->getRealPath() ?: '');

        if ($contents === false || $contents === '') {
            throw new RuntimeException('Le fichier importé est vide ou illisible.');
        }

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->importCsvString($contents, $skipDuplicates, DbCommande::SOURCE_IMPORT);
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->importExcelFile($file, $skipDuplicates, DbCommande::SOURCE_IMPORT);
        }

        throw new RuntimeException(
            'Format non pris en charge. Utilisez un fichier Excel (.xlsx, .xls) ou CSV.',
        );
    }

    /**
     * @return array{imported: int, skipped: int, errors: list<string>, total: int}
     */
    public function importExcelFile(UploadedFile $file, bool $skipDuplicates = true, string $source = DbCommande::SOURCE_IMPORT): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw new RuntimeException('Impossible de lire le fichier Excel.');
        }

        return $this->importExcelPath($path, $skipDuplicates, $source);
    }

    /**
     * @return array{imported: int, skipped: int, errors: list<string>, total: int}
     */
    public function importExcelPath(string $path, bool $skipDuplicates = true, string $source = DbCommande::SOURCE_IMPORT): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            throw new RuntimeException('Fichier Excel invalide ou illisible.');
        }

        $sheet = $spreadsheet->getSheetByName('Commandes')
            ?? $spreadsheet->getSheet($spreadsheet->getFirstSheetIndex());

        $matrix = $sheet->toArray(null, true, true, false);
        if ($matrix === []) {
            throw new RuntimeException('La feuille Excel est vide.');
        }

        $headerRowIndex = $this->detectExcelHeaderRow($matrix);
        $header = array_map(
            fn ($cell) => $this->normalizeHeader((string) ($cell ?? '')),
            $matrix[$headerRowIndex] ?? [],
        );

        if ($this->rowIsEmpty($header)) {
            throw new RuntimeException('En-têtes Excel introuvables.');
        }

        $rows = [];
        for ($i = $headerRowIndex + 1, $max = count($matrix); $i < $max; $i++) {
            $data = $matrix[$i] ?? [];
            if ($this->rowIsEmpty($data)) {
                continue;
            }

            $assoc = [];
            foreach ($header as $colIndex => $key) {
                if ($key === '') {
                    continue;
                }
                $assoc[$key] = $data[$colIndex] ?? null;
            }

            $mapped = $this->mapImportedRow($assoc);
            if ($mapped !== null) {
                $rows[] = $mapped;
            }
        }

        return $this->persistRows($rows, $skipDuplicates, $source);
    }

    /**
     * @return array{imported: int, skipped: int, errors: list<string>, total: int}
     */
    public function importCsvString(string $csv, bool $skipDuplicates = true, string $source = DbCommande::SOURCE_IMPORT): array
    {
        $csv = $this->normalizeCsvEncoding($csv);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Impossible de lire le CSV.');
        }

        fwrite($handle, $csv);
        rewind($handle);

        $header = fgetcsv($handle, 0, ';');
        if ($header === false || $header === [null] || count($header) < 2) {
            rewind($handle);
            $header = fgetcsv($handle, 0, ',');
        }

        if ($header === false || $header === [null]) {
            fclose($handle);
            throw new RuntimeException('Le CSV ne contient pas d’en-tête.');
        }

        $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $header);
        $rows = [];
        $delimiter = str_contains($csv, ';') && ! str_contains(implode('', $header), ',') ? ';' : ',';

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($data === [null] || $this->rowIsEmpty($data)) {
                continue;
            }
            $assoc = [];
            foreach ($header as $i => $key) {
                $assoc[$key] = $data[$i] ?? null;
            }
            $mapped = $this->mapImportedRow($assoc);
            if ($mapped !== null) {
                $rows[] = $mapped;
            }
        }

        fclose($handle);

        return $this->persistRows($rows, $skipDuplicates, $source);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int, errors: list<string>, total: int}
     */
    public function persistRows(array $rows, bool $skipDuplicates, string $source): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $existing = $skipDuplicates
            ? DbCommande::query()->whereNotNull('empreinte')->pluck('id', 'empreinte')
            : collect();

        DB::transaction(function () use ($rows, $skipDuplicates, $source, &$imported, &$skipped, &$errors, $existing): void {
            foreach ($rows as $index => $payload) {
                try {
                    $payload['source'] = $source;
                    $payload['empreinte'] = DbCommande::computeEmpreinteFromPayload($payload);

                    if ($skipDuplicates && $existing->has($payload['empreinte'])) {
                        $skipped++;

                        continue;
                    }

                    $commande = DbCommande::query()->create($payload);
                    $existing->put($payload['empreinte'], $commande->id);
                    $imported++;
                } catch (\Throwable $e) {
                    $errors[] = sprintf('Ligne %d : %s', $index + 1, $e->getMessage());
                }
            }
        });

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => count($rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public function mapImportedRow(array $row): ?array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$this->normalizeHeader((string) $key)] = $value;
        }

        $payload = [
            'ligne_index' => $this->toInt($normalized['ligne_index'] ?? $normalized['n_index'] ?? $normalized['n_0_index'] ?? null),
            'code_commande' => $this->toString($normalized['code_commande'] ?? null),
            'code_genere' => (bool) ($normalized['code_genere'] ?? false),
            'date_commande' => $this->toDate($normalized['date_commande'] ?? null),
            'heure_commande' => $this->toString($normalized['heure_commande'] ?? null),
            'nom_client' => $this->toString($normalized['nom_client'] ?? $normalized['nom_du_client'] ?? null),
            'sexe' => $this->toSexe($normalized['sexe'] ?? null),
            'telephone' => $this->toString($normalized['telephone'] ?? $normalized['t_el_ephone'] ?? null),
            'adresse_livraison' => $this->toString($normalized['adresse_livraison'] ?? $normalized['adresse_de_livraison'] ?? null),
            'arrondissement' => $this->toString($normalized['arrondissement'] ?? $normalized['arrondissement_ville'] ?? null),
            'pharmacie' => $this->toString($normalized['pharmacie'] ?? $normalized['pharmacies'] ?? $normalized['pharmacie_s'] ?? null),
            'medicaments' => $this->toString($normalized['medicaments'] ?? $normalized['medicaments_commandes'] ?? $normalized['m_edicaments_command_es'] ?? null),
            'quantite' => $this->toInt($normalized['quantite'] ?? $normalized['qte'] ?? $normalized['qt_e'] ?? null),
            'mode_paiement' => $this->toString($normalized['mode_paiement'] ?? $normalized['mode_de_paiement'] ?? null),
            'montant_produits' => $this->toFloat($normalized['montant_produits'] ?? null),
            'frais_livraison' => $this->toFloat($normalized['frais_livraison'] ?? null),
            'total_paye_client' => $this->toFloat($normalized['total_paye_client'] ?? $normalized['total_paye'] ?? $normalized['total_pay_e_client'] ?? null),
            'perte' => $this->toFloat($normalized['perte'] ?? null),
            'nom_livreur' => $this->toString($normalized['nom_livreur'] ?? $normalized['nom_du_livreur'] ?? null),
            'date_livraison_effective' => $this->toDate($normalized['date_livraison_effective'] ?? null),
            'delai_heures' => $this->toFloat($normalized['delai_heures'] ?? $normalized['delai_h'] ?? $normalized['d_elai_h'] ?? null),
            'statut' => $this->toStatut($normalized['statut'] ?? null),
            'ca_medicaments' => $this->toFloat($normalized['ca_medicaments'] ?? $normalized['ca_m_edicaments'] ?? null),
            'ca_parapharmacie' => $this->toFloat($normalized['ca_parapharmacie'] ?? null),
        ];

        $headerMarkers = ['code commande', 'nom du client', 'n index', 'n° index'];
        $codeLower = strtolower((string) ($payload['code_commande'] ?? ''));
        $nomLower = strtolower((string) ($payload['nom_client'] ?? ''));
        if (in_array($codeLower, $headerMarkers, true) || in_array($nomLower, $headerMarkers, true)) {
            return null;
        }

        $hasIdentity = ($payload['code_commande'] ?? null)
            || ($payload['nom_client'] ?? null)
            || ($payload['medicaments'] ?? null)
            || ($payload['telephone'] ?? null)
            || ($payload['montant_produits'] ?? null) !== null;

        if (! $hasIdentity) {
            return null;
        }

        return $payload;
    }

    /**
     * @param  callable(\Illuminate\Database\Eloquent\Builder<DbCommande>): void|null  $scope
     */
    public function writeImportsExcel(string $path, ?callable $scope = null): void
    {
        $query = DbCommande::query()->orderBy('date_commande')->orderBy('id');
        if ($scope !== null) {
            $scope($query);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Commandes');
        $sheet->fromArray($this->excelHeaders(), null, 'A1');

        $rowIndex = 2;
        foreach ($query->cursor() as $commande) {
            $sheet->fromArray([$this->excelRowFromDbCommande($commande)], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        if ($rowIndex > 2) {
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:W'.($rowIndex - 1));
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
    }

    /**
     * @return array<int, mixed>
     */
    public function excelRowFromDbCommande(DbCommande $commande): array
    {
        return [
            $commande->ligne_index,
            $commande->code_commande,
            $commande->date_commande?->format('Y-m-d'),
            $commande->heure_commande,
            $commande->nom_client,
            $commande->sexe,
            $commande->telephone,
            $commande->adresse_livraison,
            $commande->arrondissement,
            $commande->pharmacie,
            $commande->medicaments,
            $commande->quantite,
            $commande->mode_paiement,
            $commande->montant_produits,
            $commande->frais_livraison,
            $commande->total_paye_client,
            $commande->perte,
            $commande->nom_livreur,
            $commande->date_livraison_effective?->format('Y-m-d'),
            $commande->delai_heures,
            $this->statutFichierPourExcel($commande->statut),
            $commande->ca_medicaments,
            $commande->ca_parapharmacie,
        ];
    }

    private function statutFichierPourExcel(?string $statut): ?string
    {
        return DbCommande::statutFichierExcelLabel($statut);
    }

    /**
     * @return array<int, string>
     */
    public function excelHeaders(): array
    {
        return [
            'N° Index',
            'Code Commande',
            'Date Commande',
            'Heure Commande',
            'Nom du Client',
            'Sexe',
            'Téléphone',
            'Adresse de Livraison',
            'Arrondissement / Ville',
            'Pharmacie(s)',
            'Médicaments Commandés',
            'Qté',
            'Mode de Paiement',
            'Montant Produits',
            'Frais Livraison',
            'Total Payé Client',
            'Perte',
            'Nom du Livreur',
            'Date Livraison Effective',
            'Délai (h)',
            'Statut',
            'CA Médicaments',
            'CA Parapharmacie',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function modeleExcelExampleRow(): array
    {
        return [
            1,
            'BDK0101-001',
            '2026-01-15',
            '10:30:00',
            'Madame Exemple',
            'F',
            '06 000 00 00',
            'Centre-ville',
            'Poto-Poto',
            'Auréole',
            'Paracétamol 500 mg',
            1,
            'Espèces',
            2500,
            1000,
            3500,
            null,
            null,
            '2026-01-15',
            0.5,
            'Livrée',
            2500,
            0,
        ];
    }

    public function buildModeleSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Commandes');
        $sheet->fromArray($this->excelHeaders(), null, 'A1');
        $sheet->fromArray([$this->modeleExcelExampleRow()], null, 'A2');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:W2');

        return $spreadsheet;
    }

    public function writeModeleExcel(string $path): void
    {
        $writer = new Xlsx($this->buildModeleSpreadsheet());
        $writer->save($path);
    }

    /**
     * @param  list<list<mixed>>  $matrix
     */
    private function detectExcelHeaderRow(array $matrix): int
    {
        foreach ($matrix as $index => $row) {
            $first = strtolower(trim((string) ($row[0] ?? '')));
            $code = trim((string) ($row[1] ?? ''));
            if ($first !== '' && (str_contains($first, 'index') || str_starts_with($first, 'n'))) {
                return $index;
            }
            if ($code !== '' && str_starts_with(strtoupper($code), 'BDK')) {
                return max(0, $index - 1);
            }
        }

        return 0;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = mb_strtolower($header, 'UTF-8');
        $header = str_replace(['°', 'º'], '', $header);
        $header = strtr($header, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($normalized, '_');
    }

    /**
     * @param  list<mixed>  $data
     */
    private function rowIsEmpty(array $data): bool
    {
        foreach ($data as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeCsvEncoding(string $csv): string
    {
        if (str_starts_with($csv, "\xEF\xBB\xBF")) {
            return substr($csv, 3);
        }

        if (! mb_check_encoding($csv, 'UTF-8')) {
            $converted = mb_convert_encoding($csv, 'UTF-8', 'Windows-1252');

            return is_string($converted) ? $converted : $csv;
        }

        return $csv;
    }

    private function toString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function toInt(mixed $value): ?int
    {
        $float = $this->toFloat($value);

        return $float === null ? null : (int) round($float);
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        $text = preg_replace('/\s*(fcfa|xaf|cfa)\b/iu', '', $text) ?? $text;
        $text = str_replace(["\u{00a0}", ' '], '', $text);
        $text = trim($text);

        if ($text === '' || $text === '-') {
            return null;
        }

        if (preg_match('/^-?\d+(?:[.,]\d{3})+(?:[.,]\d+)?$/', $text)) {
            $lastComma = strrpos($text, ',');
            $lastDot = strrpos($text, '.');
            $decimalPos = max($lastComma !== false ? $lastComma : -1, $lastDot !== false ? $lastDot : -1);

            if ($decimalPos > 0 && strlen($text) - $decimalPos - 1 <= 2) {
                $integerPart = substr($text, 0, $decimalPos);
                $decimalPart = substr($text, $decimalPos + 1);
                $integerPart = str_replace([',', '.'], '', $integerPart);
                $text = $integerPart.'.'.$decimalPart;
            } else {
                $text = str_replace([',', '.'], '', $text);
            }
        } elseif (preg_match('/,\d{1,2}$/', $text) && substr_count($text, ',') === 1) {
            $text = str_replace(',', '.', $text);
        } elseif (str_contains($text, ',') && preg_match('/^\d{1,3}(,\d{3})+$/', $text)) {
            $text = str_replace(',', '', $text);
        } else {
            $text = str_replace(',', '.', $text);
        }

        $text = preg_replace('/[^0-9.\-]/', '', $text) ?? '';
        if ($text === '' || $text === '-' || $text === '.') {
            return null;
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private function toDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value) || is_float($value)) {
            $serial = (float) $value;
            if ($serial >= 1 && $serial <= 600000) {
                try {
                    return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($serial)->format('Y-m-d');
                } catch (\Throwable) {
                    // Continue with string parsing below.
                }
            }
        }

        $text = $this->toString($value);
        if ($text === null) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $text)) {
            return substr($text, 0, 10);
        }

        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $text, $matches)) {
            $first = (int) $matches[1];
            $second = (int) $matches[2];
            $year = (int) $matches[3];

            if ($first > 12 && $second <= 12) {
                return sprintf('%04d-%02d-%02d', $year, $second, $first);
            }
            if ($second > 12 && $first <= 12) {
                return sprintf('%04d-%02d-%02d', $year, $first, $second);
            }
        }

        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y'] as $fmt) {
            $parsed = \DateTimeImmutable::createFromFormat('!'.$fmt, substr($text, 0, 10));
            $errors = \DateTimeImmutable::getLastErrors();
            if ($parsed instanceof \DateTimeImmutable
                && ($errors['warning_count'] ?? 0) === 0
                && ($errors['error_count'] ?? 0) === 0) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    private function toSexe(mixed $value): ?string
    {
        $text = $this->toString($value);
        if ($text === null) {
            return null;
        }
        $letter = strtoupper(substr($text, 0, 1));

        return in_array($letter, ['F', 'M'], true) ? $letter : $text;
    }

    private function toStatut(mixed $value): ?string
    {
        return DbCommande::parseStatutImportValue($value);
    }
}
