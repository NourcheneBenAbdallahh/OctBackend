<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockInventaire extends Model
{
    protected $table = 'stock_inventaires';

    protected $fillable = [
        'entrepot_id',
        'emballage_id',
        'lot_id',
        'stock_physique',
        'stock_theorique',
        'ecart',
        'user_id',
        'date_inventaire',
    ];

    protected $casts = [
        'date_inventaire' => 'datetime',
        'stock_physique' => 'decimal:2',
        'stock_theorique' => 'decimal:2',
        'ecart' => 'decimal:2',
    ];

    public function entrepot(): BelongsTo
    {
        return $this->belongsTo(Entrepot::class, 'entrepot_id');
    }

    public function emballage(): BelongsTo
    {
        return $this->belongsTo(Emballage::class, 'emballage_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'lot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}