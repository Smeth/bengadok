<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Libellés par défaut (anciens motifs) — utilisé pour migrer motif_annulation_configs. */
    private function defaultRows(): array
    {
        return [
            ['slug' => 'medicaments_indisponibles', 'label' => 'Médicaments indisponibles', 'autorise_relance' => true],
            ['slug' => 'demande_patient', 'label' => 'Demande du patient', 'autorise_relance' => false],
            ['slug' => 'erreur_commande', 'label' => 'Erreur de commandes', 'autorise_relance' => false],
            ['slug' => 'probleme_paiement', 'label' => 'Problème de paiement', 'autorise_relance' => false],
            ['slug' => 'pharmacie_fermee', 'label' => 'Pharmacie fermée', 'autorise_relance' => false],
            ['slug' => 'probleme_livraison', 'label' => 'Problème de livraison', 'autorise_relance' => false],
            ['slug' => 'autre_motif', 'label' => 'Autre motif', 'autorise_relance' => false],
        ];
    }

    public function up(): void
    {
        Schema::create('motifs_annulation', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('label', 255);
            $table->boolean('autorise_relance')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $labelsBySlug = collect($this->defaultRows())->keyBy('slug');

        if (Schema::hasTable('motif_annulation_configs')) {
            $configs = DB::table('motif_annulation_configs')->orderBy('id')->get();
            $order = 0;
            foreach ($configs as $c) {
                $slug = $c->motif;
                $def = $labelsBySlug->get($slug);
                DB::table('motifs_annulation')->insert([
                    'slug' => $slug,
                    'label' => $def['label'] ?? $slug,
                    'autorise_relance' => (bool) $c->autorise_relance,
                    'sort_order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            Schema::drop('motif_annulation_configs');
        } else {
            foreach ($this->defaultRows() as $order => $row) {
                DB::table('motifs_annulation')->insert([
                    'slug' => $row['slug'],
                    'label' => $row['label'],
                    'autorise_relance' => $row['autorise_relance'],
                    'sort_order' => $order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::create('motif_annulation_configs', function (Blueprint $table) {
            $table->id();
            $table->string('motif', 100)->unique();
            $table->boolean('autorise_relance')->default(false);
            $table->timestamps();
        });

        if (Schema::hasTable('motifs_annulation')) {
            $rows = DB::table('motifs_annulation')->orderBy('sort_order')->orderBy('id')->get();
            foreach ($rows as $r) {
                DB::table('motif_annulation_configs')->insert([
                    'motif' => $r->slug,
                    'autorise_relance' => (bool) $r->autorise_relance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            Schema::drop('motifs_annulation');
        }
    }
};
