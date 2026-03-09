<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lot extends Model
{
    protected $table = 'lots';

    protected $fillable = [
        'code_lot',
        'emballage_id',
        'type_mvt',
        'quantite',
        'entrepot_source_id',
        'entrepot_dest_id',
        'user_id',
        'date_mvt',
        'commentaire',
    ];

    protected $casts = [
        'quantite' => 'decimal:2',
        'date_mvt' => 'datetime',
    ];

    public function emballage(): BelongsTo
    {
        return $this->belongsTo(Emballage::class, 'emballage_id');
    }

    public function entrepotSource(): BelongsTo
    {
        return $this->belongsTo(Entrepot::class, 'entrepot_source_id');
    }

    public function entrepotDest(): BelongsTo
    {
        return $this->belongsTo(Entrepot::class, 'entrepot_dest_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Optionnel: si tu veux retrouver les lignes "stocks" créées suite à ce lot
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'lot_id');
    }

    // Optionnel: inventaires rattachés au lot
    public function stockInventaires(): HasMany
    {
        return $this->hasMany(StockInventaire::class, 'lot_id');
    }
}