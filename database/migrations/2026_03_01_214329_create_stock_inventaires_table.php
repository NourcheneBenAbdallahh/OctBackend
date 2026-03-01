<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('stock_inventaires', function (Blueprint $table) {
    $table->id();
    $table->foreignId('entrepot_id')->constrained();
    $table->string('article_ref');
    $table->decimal('stock_theorique', 15,2);
    $table->decimal('stock_physique', 15,2);
    $table->decimal('ecart', 15,2);
    $table->foreignId('user_id')->constrained('users');
    $table->date('date_inventaire');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_inventaires');
    }
};
