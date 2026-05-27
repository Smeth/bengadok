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

    private function appliquerTimestampsDashboard(Commande $commande, string $statusAdmin): void
    {
        $base = $commande->date instanceof Carbon
            ? $commande->date->copy()->setTime(10, 30)
            : Carbon::parse($commande->date)->setTime(10, 30);

        $updates = [];

        if (in_array($statusAdmin, ['en_attente', 'validee', 'retiree', 'livree', 'a_preparer'], true)) {
            $updates['dispo_pharmacie_at'] = $base->copy()->addHours(2);
        }

        if (in_array($statusAdmin, ['validee', 'retiree', 'livree', 'a_preparer'], true)) {
            $updates['validee_admin_at'] = $base->copy()->addHours(4);
        }

        if (in_array($statusAdmin, ['retiree', 'livree'], true)) {
            $updates['livree_at'] = $base->copy()->addHours(8);
        }

        if ($updates !== []) {
            $commande->update($updates);
        }
    }

    /**
     * @param  array<int, array{produit_id: int, quantite: int, prix_unitaire: float}>  $lignes
     */
    private function synchroniserLignes(Commande $commande, array $lignes): void
    {
        if ($commande->produits()->count() > 0) {
            return;
        }

        foreach ($lignes as $ligne) {
            $commande->produits()->attach($ligne['produit_id'], [
                'quantite' => $ligne['quantite'],
                'prix_unitaire' => $ligne['prix_unitaire'],
                'status' => 'disponible',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function creerOuMettreAJourCommande(
        string $numero,
        array $attrs,
        array $lignes = [],
    ): Commande {
        $commande = Commande::firstOrCreate(['numero' => $numero], $attrs);
        $commande->update($attrs);
        $this->appliquerTimestampsDashboard($commande, (string) $attrs['status']);
        $this->synchroniserLignes($commande, $lignes);

        return $commande->fresh();
    }

    public function run(): void
    {
        $pharmaClairon = Pharmacie::where('designation', 'Pharmacie Clairon')->first()
            ?? Pharmacie::query()->orderBy('id')->first();
        $pharmacies = Pharmacie::query()->orderBy('id')->get();
        $clientDiallo = Client::where('tel', '+242 07 111 11 11')->first();
        $clientKouadio = Client::where('tel', '+242 07 222 22 22')->first();
        $clientFallback = Client::query()->orderBy('id')->first();
        $modePaiement = ModePaiement::query()->orderBy('id')->first();
        $livreur1 = Livreur::query()->orderBy('id')->first();
        $montantLivraison = MontantLivraison::query()->orderBy('id')->first();
        $marcPrincipal = Client::where('tel', '+242 06 952 67 31')->first();
        $marcDuplique = Client::where('tel', '+242 06 513 23 78')->first();

        $produitMed = Produit::where('designation', 'Doliprane')->first() ?? Produit::query()->first();
        $produitPara = Produit::where('type', 'Parapharmacie')->orderBy('id')->get();
        $para1 = $produitPara->get(0);
        $para2 = $produitPara->get(1) ?? $para1;

        if (! $pharmaClairon || ! $clientFallback || ! $modePaiement || ! $produitMed) {
            return;
        }

        $clientIdGenerique = $clientDiallo?->id ?? $clientFallback->id;
        $livDesignation = (float) ($montantLivraison?->designation ?? 0);

        for ($i = 1; $i <= 10; $i++) {
            [$statusAdmin, $statusPharmacie] = $this->statutEtPharmacieAleatoires();
            $prixMedicaments = (float) rand(5000, 50000);
            $date = now()->subDays(rand(0, 14));

            $this->creerOuMettreAJourCommande(
                'BDK'.now()->format('ymd').str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                [
                    'client_id' => $clientIdGenerique,
                    'pharmacie_id' => $pharmaClairon->id,
                    'mode_paiement_id' => $modePaiement->id,
                    'montant_livraison_id' => $montantLivraison?->id,
                    'livreur_id' => ($livreur1 && $i % 2 === 1) ? $livreur1->id : null,
                    'date' => $date,
                    'heurs' => '10:'.str_pad((string) rand(0, 59), 2, '0', STR_PAD_LEFT),
                    'prix_medicaments' => $prixMedicaments,
                    'prix_total' => $prixMedicaments + $livDesignation,
                    'status' => $statusAdmin,
                    'status_pharmacie' => $statusPharmacie,
                    'acceptation_client' => $statusAdmin === 'retiree',
                ],
                [
                    ['produit_id' => $produitMed->id, 'quantite' => rand(1, 3), 'prix_unitaire' => (float) $produitMed->pu],
                ]
            );
        }

        if ($clientKouadio) {
            $this->creerOuMettreAJourCommande(
                'BDK-PROSPECT-001',
                [
                    'client_id' => $clientKouadio->id,
                    'pharmacie_id' => $pharmaClairon->id,
                    'mode_paiement_id' => $modePaiement->id,
                    'montant_livraison_id' => $montantLivraison?->id,
                    'date' => now()->subDays(2),
                    'heurs' => '09:15',
                    'prix_medicaments' => 3500,
                    'prix_total' => 3500 + $livDesignation,
                    'status' => 'en_attente',
                    'status_pharmacie' => 'attente_confirmation',
                    'acceptation_client' => false,
                ],
                [
                    ['produit_id' => $produitMed->id, 'quantite' => 1, 'prix_unitaire' => 3500],
                ]
            );
        }

        if ($marcPrincipal) {
            $targetPrincipal = 125000;
            $prixUnitaire = (int) round($targetPrincipal / 8);
            for ($i = 1; $i <= 8; $i++) {
                $prix = $i < 8 ? $prixUnitaire : $targetPrincipal - ($prixUnitaire * 7);
                $date = $i === 8 ? Carbon::create(2026, 1, 28) : Carbon::create(2025, 10, 15)->addDays($i * 10);
                $this->creerOuMettreAJourCommande(
                    'BDK-MARC1-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    [
                        'client_id' => $marcPrincipal->id,
                        'pharmacie_id' => $pharmaClairon->id,
                        'mode_paiement_id' => $modePaiement->id,
                        'montant_livraison_id' => $montantLivraison?->id,
                        'livreur_id' => $livreur1?->id,
                        'date' => $date,
                        'heurs' => '10:30',
                        'prix_medicaments' => (float) $prix,
                        'prix_total' => (float) $prix + $livDesignation,
                        'status' => 'retiree',
                        'status_pharmacie' => 'livre',
                        'acceptation_client' => true,
                    ],
                    [
                        ['produit_id' => $produitMed->id, 'quantite' => 1, 'prix_unitaire' => (float) $prix],
                    ]
                );
            }
        }

        if ($marcDuplique) {
            for ($i = 1; $i <= 5; $i++) {
                $prix = 6000;
                $date = $i === 5 ? Carbon::create(2026, 1, 28) : Carbon::create(2026, 2, 16)->addDays($i);
                $this->creerOuMettreAJourCommande(
                    'BDK-MARC2-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    [
                        'client_id' => $marcDuplique->id,
                        'pharmacie_id' => $pharmaClairon->id,
                        'mode_paiement_id' => $modePaiement->id,
                        'montant_livraison_id' => $montantLivraison?->id,
                        'livreur_id' => $livreur1?->id,
                        'date' => $date,
                        'heurs' => '14:00',
                        'prix_medicaments' => (float) $prix,
                        'prix_total' => (float) $prix + $livDesignation,
                        'status' => 'retiree',
                        'status_pharmacie' => 'livre',
                        'acceptation_client' => true,
                    ],
                    [
                        ['produit_id' => $produitMed->id, 'quantite' => 1, 'prix_unitaire' => (float) $prix],
                    ]
                );
            }
        }

        if ($pharmaClairon && $para1) {
            $clientsMois = Client::query()
                ->when($clientKouadio, fn ($q) => $q->where('id', '!=', $clientKouadio->id))
                ->orderBy('id')
                ->limit(8)
                ->pluck('id')
                ->all();
            $debutMois = now()->startOfMonth();
            $nbCommandesMois = 32;

            $maxJour = max(1, (int) now()->day);
            for ($i = 1; $i <= $nbCommandesMois; $i++) {
                $jour = $nbCommandesMois > 1
                    ? (int) floor(($i - 1) * ($maxJour - 1) / ($nbCommandesMois - 1))
                    : 0;
                $date = $debutMois->copy()->addDays($jour);
                if ($date->isFuture()) {
                    $date = now()->copy()->subDays(rand(0, 2));
                }

                $prixPara = (float) rand(8000, 22000);
                $prixMedLigne = (float) rand(5000, 12000);
                $prixMedicaments = $prixPara + $prixMedLigne;
                $clientId = $clientsMois[($i - 1) % max(1, count($clientsMois))] ?? $clientIdGenerique;

                $lignes = [
                    ['produit_id' => $produitMed->id, 'quantite' => 1, 'prix_unitaire' => $prixMedLigne],
                    ['produit_id' => $para1->id, 'quantite' => rand(1, 2), 'prix_unitaire' => $prixPara / 2],
                ];
                if ($para2 && $i % 3 === 0) {
                    $lignes[] = ['produit_id' => $para2->id, 'quantite' => 1, 'prix_unitaire' => (float) $para2->pu];
                }

                $commande = Commande::firstOrCreate(
                    ['numero' => 'BDK-PARA-'.now()->format('Ym').'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                    [
                        'client_id' => $clientId,
                        'pharmacie_id' => $pharmaClairon->id,
                        'mode_paiement_id' => $modePaiement->id,
                        'montant_livraison_id' => $montantLivraison?->id,
                        'livreur_id' => $livreur1?->id,
                        'date' => $date,
                        'heurs' => '11:00',
                        'prix_medicaments' => $prixMedicaments,
                        'prix_total' => $prixMedicaments + $livDesignation,
                        'status' => 'retiree',
                        'status_pharmacie' => 'livre',
                        'acceptation_client' => true,
                    ]
                );

                $commande->update([
                    'client_id' => $clientId,
                    'pharmacie_id' => $pharmaClairon->id,
                    'date' => $date,
                    'prix_medicaments' => $prixMedicaments,
                    'prix_total' => $prixMedicaments + $livDesignation,
                    'status' => 'retiree',
                    'status_pharmacie' => 'livre',
                    'acceptation_client' => true,
                ]);

                if ($commande->produits()->count() === 0) {
                    foreach ($lignes as $ligne) {
                        $commande->produits()->attach($ligne['produit_id'], [
                            'quantite' => $ligne['quantite'],
                            'prix_unitaire' => $ligne['prix_unitaire'],
                            'status' => 'disponible',
                        ]);
                    }
                }

                $this->appliquerTimestampsDashboard($commande, 'retiree');
            }
        }

        foreach ($pharmacies->skip(1)->take(3) as $idx => $pharmacie) {
            for ($j = 1; $j <= 4; $j++) {
                $prix = (float) rand(6000, 18000);
                $date = now()->subMonths($idx + 1)->startOfMonth()->addDays($j * 3);
                $this->creerOuMettreAJourCommande(
                    'BDK-PH'.$pharmacie->id.'-'.str_pad((string) $j, 2, '0', STR_PAD_LEFT),
                    [
                        'client_id' => $clientIdGenerique,
                        'pharmacie_id' => $pharmacie->id,
                        'mode_paiement_id' => $modePaiement->id,
                        'montant_livraison_id' => $montantLivraison?->id,
                        'date' => $date,
                        'heurs' => '15:00',
                        'prix_medicaments' => $prix,
                        'prix_total' => $prix + $livDesignation,
                        'status' => 'retiree',
                        'status_pharmacie' => 'livre',
                        'acceptation_client' => true,
                    ],
                    $para1
                        ? [
                            ['produit_id' => $produitMed->id, 'quantite' => 1, 'prix_unitaire' => $prix * 0.4],
                            ['produit_id' => $para1->id, 'quantite' => 1, 'prix_unitaire' => $prix * 0.6],
                        ]
                        : [
                            ['produit_id' => $produitMed->id, 'quantite' => 1, 'prix_unitaire' => $prix],
                        ]
                );
            }
        }
    }
}
