<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration unifiée : création de stock_inventaires
     * - Suppression de article_ref
     * - Ajout de emballage_id (FK)
     * - Ajout de lot_id nullable (FK)
     * - stock_theorique/physique/ecart + user + date
     */
    public function up(): void
    {
        Schema::create('stock_inventaires', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entrepot_id')
                ->constrained()
                ->cascadeOnDelete();

            // Remplace article_ref
            $table->foreignId('emballage_id')
                ->constrained('emballages')
                ->cascadeOnDelete();

            // Option A : lot_id doit être nullable
            $table->foreignId('lot_id')
                ->nullable()
                ->constrained('lots')
                ->nullOnDelete();

            $table->decimal('stock_theorique', 15, 2)->default(0);
            $table->decimal('stock_physique', 15, 2)->default(0);
            $table->decimal('ecart', 15, 2)->default(0);

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Si tu veux DateTime, remplace par ->dateTime('date_inventaire')
            $table->dateTime('date_inventaire');

            $table->timestamps();

            // Optionnel (recommandé) : éviter doublons d'inventaire
            // Si tu veux 1 inventaire par (date, entrepot, emballage)
            $table->unique(['entrepot_id', 'emballage_id', 'date_inventaire'], 'uniq_inv_entrepot_emballage_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_inventaires');
    }
};