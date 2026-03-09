<?php

namespace App\GraphQL\Mutations;

use App\Models\StockInventaire;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class StockInventaireMutator
{
    public function create($_, array $args): StockInventaire
    {
        $input = $args['input'];

        return DB::transaction(function () use ($input) {
            $entrepotId  = (int) $input['entrepot_id'];
            $emballageId = (int) $input['emballage_id'];
            $userId      = $input['user_id'] ?? null;
            $dateInv     = $input['date_inventaire'];
            $physique    = (float) $input['stock_physique'];

            $theorique = app(StockService::class)->getTheoriqueAt($entrepotId, $emballageId, $dateInv);

            return StockInventaire::create([
                'entrepot_id'      => $entrepotId,
                'emballage_id'     => $emballageId,
                'lot_id'           => $input['lot_id'] ?? null,
                'stock_physique'   => $physique,
                'stock_theorique'  => $theorique,
                'ecart'            => $physique - $theorique,
                'user_id'          => $userId,
                'date_inventaire'  => $dateInv,
            ]);
        });
    }

    public function update($_, array $args): StockInventaire
    {
        $id = (int) $args['id'];
        $input = $args['input'];

        return DB::transaction(function () use ($id, $input) {
            $inv = StockInventaire::query()->findOrFail($id);

            // Valeurs cible (si non fournies, on garde l’existant)
            $dateInv = $input['date_inventaire'] ?? $inv->date_inventaire;
            $physique = array_key_exists('stock_physique', $input)
                ? (float) $input['stock_physique']
                : (float) $inv->stock_physique;

            // Recalcul du théorique à partir du système (table stocks)
            $theorique = app(StockService::class)->getTheoriqueAt(
                (int) $inv->entrepot_id,
                (int) $inv->emballage_id,
                $dateInv
            );

            $inv->fill([
                'lot_id'          => $input['lot_id'] ?? $inv->lot_id,
                'stock_physique'  => $physique,
                'stock_theorique' => $theorique,
                'ecart'           => $physique - $theorique,
                'user_id'         => $input['user_id'] ?? $inv->user_id,
                'date_inventaire' => $dateInv,
            ]);

            $inv->save();

            return $inv->fresh();
        });
    }

    public function delete($_, array $args): StockInventaire
    {
        $id = (int) $args['id'];

        return DB::transaction(function () use ($id) {
            $inv = StockInventaire::query()->findOrFail($id);
            $inv->delete();
            return $inv;
        });
    }
}