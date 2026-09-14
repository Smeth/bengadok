<?php

declare(strict_types=1);

use App\Models\DbCommande;
use App\Services\DbCommandeImportService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$source = base_path('commandes anciennes.xlsx');
$output = $argv[1] ?? base_path('commandes-anciennes-import.xlsx');

if (! is_readable($source)) {
    fwrite(STDERR, "Fichier introuvable : {$source}\n");
    exit(1);
}

$service = app(DbCommandeImportService::class);

$spreadsheet = IOFactory::load($source);
$sheet = $spreadsheet->getSheetByName('Commandes') ?? $spreadsheet->getActiveSheet();
$matrix = $sheet->toArray(null, true, true, false);

if ($matrix === []) {
    fwrite(STDERR, "Feuille Excel vide.\n");
    exit(1);
}

// Reprend la détection d'en-têtes de DbCommandeImportService (via import test).
$headerRowIndex = detectHeaderRow($matrix);
$header = array_map(
    static fn ($cell) => normalizeHeader((string) ($cell ?? '')),
    $matrix[$headerRowIndex] ?? [],
);

$outRows = [];
for ($i = $headerRowIndex + 1, $max = count($matrix); $i < $max; $i++) {
    $data = $matrix[$i] ?? [];
    if (rowIsEmpty($data)) {
        continue;
    }

    $assoc = [];
    foreach ($header as $colIndex => $key) {
        if ($key === '') {
            continue;
        }
        $assoc[$key] = $data[$colIndex] ?? null;
    }

    $mapped = $service->mapImportedRow($assoc);
    if ($mapped === null) {
        continue;
    }

    $outRows[] = mappedPayloadToExcelRow($mapped);
}

$out = new Spreadsheet;
$outSheet = $out->getActiveSheet();
$outSheet->setTitle('Commandes');
$outSheet->fromArray($service->excelHeaders(), null, 'A1');
$outSheet->fromArray($outRows, null, 'A2');
if ($outRows !== []) {
    $outSheet->freezePane('A2');
    $outSheet->setAutoFilter('A1:W'.(count($outRows) + 1));
}

(new Xlsx($out))->save($output);

echo "Fichier généré : {$output}\n";
echo 'Lignes exportées : '.count($outRows)."\n";

$testFile = new UploadedFile($output, 'commandes-anciennes-import.xlsx', null, null, true);
$test = $service->importExcelFile($testFile, false);
echo "Validation import :\n";
print_r($test);

/**
 * @param  list<list<mixed>>  $matrix
 */
function detectHeaderRow(array $matrix): int
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

function normalizeHeader(string $header): string
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
function rowIsEmpty(array $data): bool
{
    foreach ($data as $cell) {
        if ($cell !== null && trim((string) $cell) !== '') {
            return false;
        }
    }

    return true;
}

/**
 * @param  array<string, mixed>  $mapped
 * @return list<mixed>
 */
function mappedPayloadToExcelRow(array $mapped): array
{
    $statutKey = $mapped['statut'] ?? null;

    return [
        $mapped['ligne_index'] ?? null,
        $mapped['code_commande'] ?? null,
        formatDateForExcel($mapped['date_commande'] ?? null),
        formatTimeForExcel($mapped['heure_commande'] ?? null),
        $mapped['nom_client'] ?? null,
        $mapped['sexe'] ?? null,
        $mapped['telephone'] ?? null,
        $mapped['adresse_livraison'] ?? null,
        $mapped['arrondissement'] ?? null,
        $mapped['pharmacie'] ?? null,
        $mapped['medicaments'] ?? null,
        $mapped['quantite'] ?? null,
        $mapped['mode_paiement'] ?? null,
        $mapped['montant_produits'] ?? null,
        $mapped['frais_livraison'] ?? null,
        $mapped['total_paye_client'] ?? null,
        $mapped['perte'] ?? null,
        $mapped['nom_livreur'] ?? null,
        formatDateForExcel($mapped['date_livraison_effective'] ?? null),
        $mapped['delai_heures'] ?? null,
        $statutKey ? DbCommande::statutFichierExcelLabel((string) $statutKey) : null,
        $mapped['ca_medicaments'] ?? null,
        $mapped['ca_parapharmacie'] ?? null,
    ];
}

function formatDateForExcel(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }

    return (string) $value;
}

function formatTimeForExcel(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('H:i:s');
    }

    return (string) $value;
}
