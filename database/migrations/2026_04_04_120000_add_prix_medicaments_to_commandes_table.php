<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->decimal('prix_medicaments', 12, 2)->default(0)->after('prix_total');
        });

        // Somme des lignes produits (hors livraison)
        DB::statement("
            UPDATE commandes c
            SET prix_medicaments = COALESCE((
                SELECT SUM(
                    CASE
                        WHEN cp.status = 'indisponible' THEN 0
                        ELSE COALESCE(cp.quantite_confirmee, cp.quantite) * cp.prix_unitaire
                    END
                )
                FROM commande_produit cp
                WHERE cp.commande_id = c.id
            ), 0)
        ");

        // Si aucune ligne pivot (données anciennes), reprendre l’ancien total comme montant médicaments
        DB::table('commandes')
            ->whereRaw('prix_medicaments = 0')
            ->whereRaw('NOT EXISTS (SELECT 1 FROM commande_produit cp WHERE cp.commande_id = commandes.id)')
            ->update(['prix_medicaments' => DB::raw('prix_total')]);

        // On ne modifie pas prix_total en masse : les commandes scindées (parent/enfant)
        // partagent parfois un seul frais de livraison ; les lignes existantes restent inchangées.
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('prix_medicaments');
        });
    }
};
