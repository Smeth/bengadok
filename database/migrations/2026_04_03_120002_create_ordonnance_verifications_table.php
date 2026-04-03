<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordonnance_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordonnance_id')->constrained('ordonnances')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->longText('ocr_text')->nullable();
            $table->date('parsed_prescription_date')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('decision', 20)->default('pending')->index();
            $table->json('rule_results')->nullable();
            $table->json('flags')->nullable();
            $table->foreignId('duplicate_of_ordonnance_id')->nullable()->constrained('ordonnances')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('ordonnance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordonnance_verifications');
    }
};
