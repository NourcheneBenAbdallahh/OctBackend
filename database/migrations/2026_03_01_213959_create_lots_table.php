<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();

            $table->string('code_lot')->unique();

            $table->foreignId('emballage_id')
                  ->constrained('emballages')
                  ->cascadeOnDelete();

            $table->enum('type_mvt', ['ENTREE','SORTIE','TRANSFERT','AJUSTEMENT']);

            $table->decimal('quantite', 15, 2);

            $table->foreignId('entrepot_source_id')
                  ->nullable()
                  ->constrained('entrepots')
                  ->nullOnDelete();

            $table->foreignId('entrepot_dest_id')
                  ->nullable()
                  ->constrained('entrepots')
                  ->nullOnDelete();

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->dateTime('date_mvt');

            $table->text('commentaire')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};