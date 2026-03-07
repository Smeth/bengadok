<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\Pharmacie;
use App\Models\Produit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CommandeSeeder extends Seeder
{
    public function run(): void
    {
        $pharma1 = Pharmacie::first();
        $clientDiallo = Client::where('tel', '+242 07 111 11 11')->first();
        $modePaiement = ModePaiement::first();
        $livreur1 = Livreur::first();
        $marcPrincipal = Client::where('tel', '+242 06 952 67 31')->first();
        $marcDuplique = Client::where('tel', '+242 06 513 23 78')->first();

        // Commandes exemple pour Diallo et autres clients
        for ($i = 1; $i <= 10; $i++) {
            $commande = Commande::firstOrCreate(
                ['numero' => 'BDK'.now()->format('ymd').str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                [
                    'client_id' => $clientDiallo?->id ?? Client::first()->id,
                    'pharmacie_id' => $pharma1->id,
                    'mode_paiement_id' => $modePaiement->id,
                    'livreur_id' => $i % 2 ? $livreur1->id : null,
                    'date' => now()->subDays(rand(0, 14)),
                    'heurs' => '10:'.str_pad((string) rand(0, 59), 2, '0'),
                    'prix_total' => rand(5000, 50000),
                    'status' => ['nouvelle', 'en_attente', 'validee', 'livree'][array_rand(['nouvelle', 'en_attente', 'validee', 'livree'])],
                ]
            );
            if ($commande->produits()->count() === 0) {
                $prods = Produit::inRandomOrder()->limit(2)->get();
                foreach ($prods as $p) {
                    $commande->produits()->attach($p->id, [
                        'quantite' => rand(1, 3),
                        'prix_unitaire' => $p->pu,
                        'status' => 'disponible',
                    ]);
                }
            }
        }

        // Commandes pour Marc Mig's principal : 8 commandes, total 125 000 F, dernière le 28/01/2026
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
                        'livreur_id' => $livreur1->id,
                        'date' => $date,
                        'heurs' => '10:30',
                        'prix_total' => $prix,
                        'status' => 'livree',
                    ]
                );
                if ($commande->produits()->count() === 0) {
                    $p = Produit::first();
                    $commande->produits()->attach($p->id, ['quantite' => 1, 'prix_unitaire' => $prix, 'status' => 'disponible']);
                }
            }
        }

        // Commandes pour Marc Mig's dupliqué : 5 commandes, total 30 000 F, dernière le 28/01/2026
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
                        'livreur_id' => $livreur1->id,
                        'date' => $date,
                        'heurs' => '14:00',
                        'prix_total' => $prix,
                        'status' => 'livree',
                    ]
                );
                if ($commande->produits()->count() === 0) {
                    $p = Produit::first();
                    $commande->produits()->attach($p->id, ['quantite' => 1, 'prix_unitaire' => $prix, 'status' => 'disponible']);
                }
            }
        }
    }
}
