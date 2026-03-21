<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Clés historiques (avant table motifs_annulation dynamique). */
    private function motifKeys(): array
    {
        return [
            'medicaments_indisponibles',
            'demande_patient',
            'erreur_commande',
            'probleme_paiement',
            'pharmacie_fermee',
            'probleme_livraison',
            'autre_motif',
        ];
    }

    public function up(): void
    {
        Schema::create('motif_annulation_configs', function (Blueprint $table) {
            $table->id();
            $table->string('motif', 100)->unique();
            $table->boolean('autorise_relance')->default(false);
            $table->timestamps();
        });

        $now = now();
        foreach ($this->motifKeys() as $motif) {
            DB::table('motif_annulation_configs')->insert([
                'motif' => $motif,
                'autorise_relance' => $motif === 'medicaments_indisponibles',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('motif_annulation_configs');
    }
};
