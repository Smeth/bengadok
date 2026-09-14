<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('db_commandes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ligne_index')->nullable();
            $table->string('code_commande', 80)->nullable()->index();
            $table->boolean('code_genere')->default(false);
            $table->string('semaine', 80)->nullable();
            $table->date('date_commande')->nullable()->index();
            $table->string('heure_commande', 20)->nullable();
            $table->string('nom_client')->nullable();
            $table->string('sexe', 10)->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('adresse_livraison', 500)->nullable();
            $table->string('arrondissement', 120)->nullable();
            $table->string('pharmacie')->nullable()->index();
            $table->text('medicaments')->nullable();
            $table->unsignedInteger('quantite')->nullable();
            $table->string('mode_paiement', 80)->nullable();
            $table->decimal('montant_produits', 12, 2)->nullable();
            $table->decimal('frais_livraison', 12, 2)->nullable();
            $table->decimal('total_paye_client', 12, 2)->nullable();
            $table->decimal('montant_du_theorique', 12, 2)->nullable();
            $table->decimal('perte', 12, 2)->nullable();
            $table->string('nom_livreur', 120)->nullable();
            $table->date('date_livraison_effective')->nullable();
            $table->decimal('delai_heures', 8, 2)->nullable();
            $table->string('statut', 50)->nullable()->index();
            $table->decimal('ca_medicaments', 12, 2)->nullable();
            $table->decimal('ca_parapharmacie', 12, 2)->nullable();
            $table->text('motif_perte')->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 20)->default('manuel');
            $table->string('empreinte', 64)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_commandes');
    }
};
