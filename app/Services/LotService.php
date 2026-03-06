<?php

namespace App\Services;

use App\Models\Lot;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LotService
{
    public function createLotAndApply(array $input): Lot
    {
        return DB::transaction(function () use ($input) {

            // 1) Validation métier minimale
            $type = $input['type_mvt'];

            if ($type === 'ENTREE' && empty($input['entrepot_dest_id'])) {
                throw new InvalidArgumentException("ENTREE requiert entrepot_dest_id.");
            }
            if ($type === 'SORTIE' && empty($input['entrepot_source_id'])) {
                throw new InvalidArgumentException("SORTIE requiert entrepot_source_id.");
            }
            if ($type === 'TRANSFERT' && (empty($input['entrepot_source_id']) || empty($input['entrepot_dest_id']))) {
                throw new InvalidArgumentException("TRANSFERT requiert entrepot_source_id et entrepot_dest_id.");
            }

            // 2) Créer le lot
            $lot = Lot::create([
                'code_lot'           => $input['code_lot'],
                'emballage_id'       => $input['emballage_id'],
                'type_mvt'           => $type,
                'quantite'           => $input['quantite'],
                'entrepot_source_id' => $input['entrepot_source_id'] ?? null,
                'entrepot_dest_id'   => $input['entrepot_dest_id'] ?? null,
                'user_id'            => $input['user_id'] ?? null,
                'date_mvt'           => $input['date_mvt'],
                'commentaire'        => $input['commentaire'] ?? null,
            ]);

            // 3) Appliquer au stock (journal théorique)
            app(StockService::class)->applyLotToStockJournal($lot);

            return $lot->refresh();
        });
    }
}