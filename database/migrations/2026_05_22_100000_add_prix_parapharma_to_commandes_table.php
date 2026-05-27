<?php

use App\Models\Commande;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->decimal('prix_parapharma', 12, 2)->default(0)->after('prix_medicaments');
        });

        Commande::query()->with('produits')->chunkById(100, function ($commandes) {
            foreach ($commandes as $commande) {
                $montants = Commande::computeMontantsFromProduits($commande->produits, false);
                $commande->update([
                    'prix_medicaments' => $montants['prix_medicaments'],
                    'prix_parapharma' => $montants['prix_parapharma'],
                    'prix_total' => $montants['prix_lignes'] + $commande->montantLivraisonClient(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('prix_parapharma');
        });
    }
};
