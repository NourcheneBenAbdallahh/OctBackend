<?php

namespace App\Services;

use App\Models\StockInventaire;
use Illuminate\Support\Facades\DB;

class StockInventaireService
{
    public function createInventaire(array $input): StockInventaire
    {
        return DB::transaction(function () use ($input) {

            $theorique = app(StockService::class)->getTheoriqueAt(
                entrepotId: $input['entrepot_id'],
                emballageId: $input['emballage_id'],
                dateTime: $input['date_inventaire']
            );

            $physique = (float) $input['stock_physique'];
            $ecart = $physique - $theorique;

            return StockInventaire::create([
                'entrepot_id'      => $input['entrepot_id'],
                'emballage_id'     => $input['emballage_id'],
                'stock_theorique'  => $theorique,               // système
                'stock_physique'   => $physique,                // comptage
                'ecart'            => $ecart,
                'user_id'          => $input['user_id'] ?? null,
                'date_inventaire'  => $input['date_inventaire'],
            ]);
        });
    }
    public function removeInventairesFromLot(Lot $lot): void
{
    \App\Models\StockInventaire::where('lot_id', $lot->id)->delete();
}

}