<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\User;
use App\Support\CommandeMedicamentsResume;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CommandeExportService
{
    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return [
            'N° Commande',
            'Date',
            'Heure',
            'Statut',
            'Client',
            'Téléphone',
            'Adresse',
            'Arrondissement',
            'Pharmacie',
            'Médicaments',
            'Montant médicaments',
            'Montant parapharma',
            'Frais livraison',
            'Total',
            'Mode paiement',
            'Livreur',
            'Bénéficiaire',
            'Commentaire',
        ];
    }

    public function buildSpreadsheet(User $user, ?string $search = null, ?string $status = null, ?string $periode = null, ?string $date = null): Spreadsheet
    {
        $query = $this->exportQuery($user, $search, $status, $periode, $date);
        $commandes = $query
            ->with([
                'client:id,nom,prenom,tel,adresse,arrondissement,sexe',
                'pharmacie:id,designation',
                'produits:id,designation,dosage',
                'modePaiement:id,designation',
                'livreur:id,nom,prenom',
                'montantLivraison:id,designation',
            ])
            ->orderByRaw('COALESCE(commandes.date, DATE(commandes.created_at)) DESC')
            ->orderByDesc('commandes.id')
            ->limit(5000)
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Commandes');
        $sheet->fromArray($this->headers(), null, 'A1');

        $rowIndex = 2;
        foreach ($commandes as $commande) {
            $client = $commande->client;
            $livreur = $commande->livreur;
            $sheet->fromArray([[
                $commande->numero,
                $commande->date?->format('Y-m-d'),
                $commande->heurs,
                $commande->status,
                trim(($client?->prenom ?? '').' '.($client?->nom ?? '')),
                $client?->tel,
                $client?->adresse,
                $client?->arrondissement,
                $commande->pharmacie?->designation,
                CommandeMedicamentsResume::fromCollection($commande->produits),
                (float) $commande->prix_medicaments,
                (float) $commande->prix_parapharma,
                (float) ($commande->montantLivraison?->designation ?? 0),
                (float) $commande->prix_total,
                $commande->modePaiement?->designation,
                $livreur ? trim($livreur->prenom.' '.$livreur->nom) : null,
                $commande->beneficiaire,
                $commande->commentaire,
            ]], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    public function writeExcel(string $path, User $user, ?string $search = null, ?string $status = null, ?string $periode = null, ?string $date = null): void
    {
        $writer = new Xlsx($this->buildSpreadsheet($user, $search, $status, $periode, $date));
        $writer->save($path);
    }

    private function exportQuery(User $user, ?string $search, ?string $status, ?string $periode, ?string $date): Builder
    {
        $query = Commande::query()
            ->when($user->pharmacie_id, fn ($q) => $q->where('pharmacie_id', $user->pharmacie_id))
            ->when($user->hasAnyRole(['admin', 'super_admin', 'agent_call_center']), fn ($q) => $q->whereNull('parent_id'));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%")
                        ->orWhere('tel', 'like', "%{$search}%"));
            });
        }

        if ($status) {
            if ($status === 'validee') {
                $query->whereIn('status', ['validee', 'a_preparer']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($date) {
            $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) = ?', [$date]);

            return $query;
        }

        if ($periode) {
            match ($periode) {
                'aujourdhui' => $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) = ?', [now()->toDateString()]),
                'semaine' => $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) >= ?', [now()->copy()->startOfWeek()->toDateString()]),
                'mois' => $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) >= ?', [now()->copy()->startOfMonth()->toDateString()]),
                'annee' => $query->whereRaw('COALESCE(commandes.date, DATE(commandes.created_at)) >= ?', [now()->copy()->startOfYear()->toDateString()]),
                default => null,
            };
        }

        return $query;
    }
}
