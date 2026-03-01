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
        Schema::create('stocks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('entrepot_id')->constrained();
    $table->string('article_ref');
    $table->decimal('quantite_actuelle', 15,2)->default(0);
    $table->unique(['entrepot_id','article_ref']);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
