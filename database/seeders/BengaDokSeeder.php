<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Heur;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\MontantLivraison;
use App\Models\Ordonnance;
use App\Models\Pharmacie;
use App\Models\Produit;
use App\Models\TypePharmacie;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class BengaDokSeeder extends Seeder
{
    public function run(): void
    {
        // Zones (Brazzaville)
        $zones = [
            ['designation' => 'Moungali'],
            ['designation' => 'Poto-Poto'],
            ['designation' => 'Bacongo'],
            ['designation' => 'Makélékélé'],
            ['designation' => 'Ovenzé'],
            ['designation' => 'Mfilou'],
        ];
        foreach ($zones as $z) {
            Zone::firstOrCreate($z);
        }

        // Heures d'ouverture
        $heurs = Heur::firstOrCreate(
            ['ouverture' => '08:00', 'fermeture' => '20:00']
        );

        // Types de pharmacie
        $typeJour = TypePharmacie::firstOrCreate(
            ['designation' => 'Pharmacie de Jour'],
            ['heurs_id' => $heurs->id]
        );
        TypePharmacie::firstOrCreate(
            ['designation' => 'Pharmacie de Garde'],
            ['heurs_id' => $heurs->id]
        );

        // Pharmacies
        $pharmacies = [];
        foreach (Zone::all() as $zone) {
            $pharmacies[] = Pharmacie::firstOrCreate(
                [
                    'designation' => "Pharmacie {$zone->designation}",
                    'zone_id' => $zone->id,
                ],
                [
                    'type_pharmacie_id' => $typeJour->id,
                    'heurs_id' => $heurs->id,
                    'telephone' => '+242 01 234 56 78',
                    'adresse' => "Avenue principale, {$zone->designation}",
                    'email' => "pharma.{$zone->id}@bengadok.cg",
                    'proprio_nom' => 'Dr. Propriétaire',
                ]
            );
        }

        // Produits (médicaments)
        $produits = [
            ['designation' => 'Paracétamol', 'dosage' => '500mg', 'forme' => 'Comprimé', 'pu' => 500],
            ['designation' => 'Oméprazole', 'dosage' => '20mg', 'forme' => 'Gélule', 'pu' => 1500],
            ['designation' => 'Ibuprofène', 'dosage' => '400mg', 'forme' => 'Comprimé', 'pu' => 800],
            ['designation' => 'Temesta', 'dosage' => '2.5mg', 'forme' => 'Comprimé', 'pu' => 5550],
            ['designation' => 'Antalgex', 'forme' => 'Sirop', 'pu' => 1600],
            ['designation' => 'Vivagest', 'forme' => 'Gélule', 'pu' => 3500],
            ['designation' => 'Glibenclamide', 'dosage' => '5mg', 'forme' => 'Comprimé', 'pu' => 2500],
            ['designation' => 'Levothyrox', 'dosage' => '50µg', 'forme' => 'Comprimé', 'pu' => 4000],
            ['designation' => 'Aldactone', 'dosage' => '50mg', 'forme' => 'Comprimé', 'pu' => 3500],
            ['designation' => 'Clipal', 'dosage' => '300mg', 'forme' => 'Comprimé', 'pu' => 2800],
        ];
        foreach ($produits as $p) {
            $produit = Produit::firstOrCreate(
                ['designation' => $p['designation']],
                array_merge($p, ['dosage' => $p['dosage'] ?? null, 'forme' => $p['forme'] ?? null, 'type' => 'Médicament'])
            );
            foreach ($pharmacies as $pharma) {
                $produit->pharmacies()->syncWithoutDetaching([
                    $pharma->id => ['stock' => rand(10, 100), 'prix' => $produit->pu],
                ]);
            }
        }

        // Modes de paiement
        ModePaiement::firstOrCreate(['designation' => 'Espèces']);
        ModePaiement::firstOrCreate(['designation' => 'Carte bancaire']);
        ModePaiement::firstOrCreate(['designation' => 'Mobile Money']);

        // Montants livraison
        MontantLivraison::firstOrCreate(['designation' => 1000]);
        MontantLivraison::firstOrCreate(['designation' => 1500]);
        MontantLivraison::firstOrCreate(['designation' => 2000]);

        // Livreurs
        $livreurs = [
            ['nom' => 'Mbemba', 'prenom' => 'Jean', 'tel' => '+242 06 111 22 33'],
            ['nom' => 'Nzouzi', 'prenom' => 'Pierre', 'tel' => '+242 06 222 33 44'],
        ];
        foreach ($livreurs as $l) {
            Livreur::firstOrCreate($l);
        }

        // Clients
        $clients = [
            ['nom' => 'Diallo', 'prenom' => 'Amélia', 'tel' => '+242 07 111 11 11', 'adresse' => 'Quartier Moungali', 'sexe' => 'F'],
            ['nom' => 'Kouadio', 'prenom' => 'Ludovic', 'tel' => '+242 07 222 22 22', 'adresse' => 'Avenue Poto-Poto', 'sexe' => 'M'],
            ['nom' => 'Traoré', 'prenom' => 'Louis', 'tel' => '+242 07 333 33 33', 'adresse' => 'Rue Bacongo', 'sexe' => 'M'],
        ];
        foreach ($clients as $c) {
            Client::firstOrCreate(['tel' => $c['tel']], $c);
        }

        // Commandes exemple
        $pharma1 = Pharmacie::first();
        $client1 = Client::first();
        $modePaiement = ModePaiement::first();
        $livreur1 = Livreur::first();

        for ($i = 1; $i <= 10; $i++) {
            $commande = Commande::firstOrCreate(
                ['numero' => 'BDK' . now()->format('ymd') . str_pad($i, 3, '0', STR_PAD_LEFT)],
                [
                    'client_id' => $client1->id,
                    'pharmacie_id' => $pharma1->id,
                    'mode_paiement_id' => $modePaiement->id,
                    'livreur_id' => $i % 2 ? $livreur1->id : null,
                    'date' => now()->subDays(rand(0, 14)),
                    'heurs' => '10:' . str_pad(rand(0, 59), 2, '0'),
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

        // Utilisateurs par rôle
        $gerant = User::firstOrCreate(
            ['email' => 'gerant@bengadok.cg'],
            ['name' => 'Marc Mig\'s Admin', 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );
        $gerant->assignRole('gerant');

        $vendeur = User::firstOrCreate(
            ['email' => 'vendeur@bengadok.cg'],
            ['name' => 'Vendeur Pharma', 'password' => bcrypt('password'), 'pharmacie_id' => $pharma1->id ?? 1, 'email_verified_at' => now()]
        );
        $vendeur->assignRole('vendeur');

        $agent = User::firstOrCreate(
            ['email' => 'agent@bengadok.cg'],
            ['name' => 'Agent Call Center', 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );
        $agent->assignRole('agent_call_center');
    }
}
