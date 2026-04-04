<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\MontantLivraison;
use App\Models\Pharmacie;
use App\Models\Produit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CommandeSeeder extends Seeder
{
    /**
     * Couples cohérents avec le workflow admin / pharmacie (voir CommandeController::updateStatus).
     *
     * @return array{0: string, 1: string}
     */
    private function statutEtPharmacieAleatoires(): array
    {
        $pairs = [
            ['nouvelle', 'nouvelle'],
            ['en_attente', 'attente_confirmation'],
            ['validee', 'valide_a_preparer'],
            ['retiree', 'livre'],
        ];

        return $pairs[array_rand($pairs)];
    }

    public function run(): void
    {
        $pharma1 = Pharmacie::query()->orderBy('id')->first();
        $clientDiallo = Client::where('tel', '+242 07 111 11 11')->first();
        $clientFallback = Client::query()->orderBy('id')->first();
        $modePaiement = ModePaiement::query()->orderBy('id')->first();
        $livreur1 = Livreur::query()->orderBy('id')->first();
        $montantLivraison = MontantLivraison::query()->orderBy('id')->first();
        $marcPrincipal = Client::where('tel', '+242 06 952 67 31')->first();
        $marcDuplique = Client::where('tel', '+242 06 513 23 78')->first();

        if (! $pharma1 || ! $clientFallback || ! $modePaiement) {
            return;
        }

        $clientIdPourGeneriques = $clientDiallo?->id ?? $clientFallback->id;

        for ($i = 1; $i <= 10; $i++) {
            [$statusAdmin, $statusPharmacie] = $this->statutEtPharmacieAleatoires();
            $acceptationClient = $statusAdmin === 'retiree';

            $commande = Commande::firstOrCreate(
                ['numero' => 'BDK'.now()->format('ymd').str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                [
                    'client_id' => $clientIdPourGeneriques,
                    'pharmacie_id' => $pharma1->id,
                    'mode_paiement_id' => $modePaiement->id,
                    'montant_livraison_id' => $montantLivraison?->id,
                    'livreur_id' => ($livreur1 && $i % 2 === 1) ? $livreur1->id : null,
                    'date' => now()->subDays(rand(0, 14)),
                    'heurs' => '10:'.str_pad((string) rand(0, 59), 2, '0', STR_PAD_LEFT),
                    'prix_total' => rand(5000, 50000),
                    'status' => $statusAdmin,
                    'status_pharmacie' => $statusPharmacie,
                    'acceptation_client' => $acceptationClient,
                ]
            );
            if ($commande->produits()->count() === 0) {
                $prods = Produit::query()->inRandomOrder()->limit(2)->get();
                foreach ($prods as $p) {
                    $commande->produits()->attach($p->id, [
                        'quantite' => rand(1, 3),
                        'prix_unitaire' => $p->pu,
                        'status' => 'disponible',
                    ]);
                }
            }
        }

        if ($marcPrincipal) {
            $targetPrincipal = 125000;
            $prixUnitaire = (int) round($targetPrincipal / 8);
            for ($i = 1; $i <= 8; $i++) {
                $prix = $i < 8 ? $prixUnitaire : $targetPrincipal - ($prixUnitaire * 7);
                $date = $i === 8 ? Carbon::create(2026, 1, 28) : Carbon::create(2025, 10, 15)->addDays($i * 10);
                $commande = Commande::firstOrCreate(
                    ['numero' => 'BDK-MARC1-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                    [
                        'client_id' => $marcPrincipal->id,
                        'pharmacie_id' => $pharma1->id,
                        'mode_paiement_id' => $modePaiement->id,
                        'montant_livraison_id' => $montantLivraison?->id,
                        'livreur_id' => $livreur1?->id,
                        'date' => $date,
                        'heurs' => '10:30',
                        'prix_total' => $prix,
                        'status' => 'retiree',
                        'status_pharmacie' => 'livre',
                        'acceptation_client' => true,
                    ]
                );
                if ($commande->produits()->count() === 0) {
                    $p = Produit::query()->first();
                    if ($p) {
                        $commande->produits()->attach($p->id, ['quantite' => 1, 'prix_unitaire' => $prix, 'status' => 'disponible']);
                    }
                }
            }
        }

        if ($marcDuplique) {
            for ($i = 1; $i <= 5; $i++) {
                $prix = 6000;
                $date = $i === 5 ? Carbon::create(2026, 1, 28) : Carbon::create(2026, 2, 16)->addDays($i);
                $commande = Commande::firstOrCreate(
                    ['numero' => 'BDK-MARC2-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                    [
                        'client_id' => $marcDuplique->id,
                        'pharmacie_id' => $pharma1->id,
                        'mode_paiement_id' => $modePaiement->id,
                        'montant_livraison_id' => $montantLivraison?->id,
                        'livreur_id' => $livreur1?->id,
                        'date' => $date,
                        'heurs' => '14:00',
                        'prix_total' => $prix,
                        'status' => 'retiree',
                        'status_pharmacie' => 'livre',
                        'acceptation_client' => true,
                    ]
                );
                if ($commande->produits()->count() === 0) {
                    $p = Produit::query()->first();
                    if ($p) {
                        $commande->produits()->attach($p->id, ['quantite' => 1, 'prix_unitaire' => $prix, 'status' => 'disponible']);
                    }
                }
            }
        }
    }
}
