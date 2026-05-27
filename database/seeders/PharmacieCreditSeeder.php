<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Commande;
use App\Models\CommissionPeriode;
use App\Models\Pharmacie;
use App\Models\PharmacieCreditOperation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PharmacieCreditSeeder extends Seeder
{
    public function run(): void
    {
        PharmacieCreditOperation::query()->delete();

        $cfg = AppSetting::parapharmaConfig();
        $admin = User::where('email', 'admin@bengadok.cg')->first();
        $seuil = $cfg['credit_seuil_medicament_xaf'];
        $prixUnitaire = $cfg['credit_prix_unitaire_xaf'];
        $commissionPercent = $cfg['commission_percent'];

        [$debutPeriode, $finPeriode] = AppSetting::parapharmaPeriodeBounds();

        foreach (Pharmacie::query()->orderBy('id')->get() as $index => $pharmacie) {
            $eligibleMois = Commande::query()
                ->where('pharmacie_id', $pharmacie->id)
                ->whereBetween('date', [$debutPeriode, $finPeriode])
                ->whereIn('status', Commande::STATUTS_REUSSIS)
                ->where('prix_medicaments', '>=', $seuil)
                ->orderBy('date')
                ->orderBy('id')
                ->get();

            $nbDeductions = $eligibleMois->count();
            $creditsDisponiblesCible = $index === 0 ? 18 : max(5, 25 - (int) ($nbDeductions / 2));
            $rechargeInitiale = $creditsDisponiblesCible + $nbDeductions;

            $solde = 0;

            PharmacieCreditOperation::query()->create([
                'pharmacie_id' => $pharmacie->id,
                'user_id' => $admin?->id,
                'type' => PharmacieCreditOperation::TYPE_RECHARGE,
                'credits_delta' => $rechargeInitiale,
                'cout_xaf' => $rechargeInitiale * $prixUnitaire,
                'solde_apres' => $rechargeInitiale,
                'mode_paiement' => 'solde_interne',
                'description' => 'Recharge manuelle de crédits (seed)',
                'note' => 'Recharge initiale démo',
                'created_at' => $debutPeriode->copy()->addHours(8),
                'updated_at' => $debutPeriode->copy()->addHours(8),
            ]);
            $solde = $rechargeInitiale;

            foreach ($eligibleMois as $commande) {
                $solde = max(0, $solde - 1);
                $numero = $commande->numero ?? '#CMD'.str_pad((string) $commande->id, 5, '0', STR_PAD_LEFT);
                $at = $commande->livree_at ?? $commande->validee_admin_at ?? $commande->updated_at ?? now();

                PharmacieCreditOperation::query()->create([
                    'pharmacie_id' => $pharmacie->id,
                    'user_id' => null,
                    'commande_id' => $commande->id,
                    'type' => PharmacieCreditOperation::TYPE_DEDUCTION,
                    'credits_delta' => -1,
                    'cout_xaf' => $prixUnitaire,
                    'solde_apres' => $solde,
                    'mode_paiement' => null,
                    'description' => "Commande {$numero}",
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }

            $pharmacie->update([
                'credits_solde' => $solde,
                'note_interne' => $index === 0
                    ? "Client régulier, préfère être contacté le matin.\nPharmacie pilote démo BengaDok."
                    : null,
                'credits_alerte_seuil' => $index === 0 ? 5 : null,
            ]);
        }

        $this->seedCommissionPeriodes($commissionPercent);
    }

    private function seedCommissionPeriodes(float $commissionPercent): void
    {
        $types = AppSetting::parapharmaConfig()['produit_types'];

        for ($i = 0; $i <= 6; $i++) {
            $m = now()->copy()->subMonths($i);
            [$debut, $fin] = AppSetting::parapharmaPeriodeBounds($m);

            $caQuery = DB::table('commande_produit')
                ->join('commandes', 'commandes.id', '=', 'commande_produit.commande_id')
                ->join('produits', 'produits.id', '=', 'commande_produit.produit_id')
                ->whereBetween('commandes.date', [$debut, $fin])
                ->whereIn('commandes.status', Commande::STATUTS_STATS_VENTES)
                ->where(function ($q) {
                    $q->whereNull('commande_produit.status')
                        ->orWhere('commande_produit.status', '<>', 'indisponible');
                });

            if ($types !== []) {
                $caQuery->whereIn('produits.type', $types);
            } else {
                $caQuery->whereRaw("LOWER(produits.type) LIKE '%parapharm%'");
            }

            $ca = (float) $caQuery
                ->selectRaw('COALESCE(SUM(commande_produit.prix_unitaire * COALESCE(commande_produit.quantite_confirmee, commande_produit.quantite)), 0) as total')
                ->value('total');

            $montant = (int) round($ca * $commissionPercent / 100);

            CommissionPeriode::query()->updateOrCreate(
                ['annee' => $m->year, 'mois' => $m->month],
                [
                    'montant' => $montant,
                    'statut' => $i === 0
                        ? CommissionPeriode::STATUT_EN_COURS
                        : CommissionPeriode::STATUT_PAYE,
                    'paye_le' => $i === 0 ? null : $fin->copy()->addDays(2),
                ]
            );
        }
    }
}
