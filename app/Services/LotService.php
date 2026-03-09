<?php

namespace App\Services;

use App\Models\Lot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LotService
{
    public function __construct(
        private StockService $stockService,
        private StockInventaireService $inventaireService,
    ) {}

    public function createLotAndApply(array $payload): Lot
    {
        return DB::transaction(function () use ($payload) {

            $this->validatePayload($payload);

            $lot = Lot::create([
                'code_lot' => $payload['code_lot'],
                'emballage_id' => (int) $payload['emballage_id'],
                'type_mvt' => $payload['type_mvt'],
                'quantite' => (float) $payload['quantite'],
                'entrepot_source_id' => $payload['entrepot_source_id'] ?? null,
                'entrepot_dest_id' => $payload['entrepot_dest_id'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
                'date_mvt' => $payload['date_mvt'],
                'commentaire' => $payload['commentaire'] ?? null,
            ]);

            $stocks = $this->stockService->applyLotToStocks($lot);

         

            return $lot;
        });
    }

    private function validatePayload(array $p): void
    {
        $type = $p['type_mvt'] ?? null;

        if (!in_array($type, ['ENTREE','SORTIE','TRANSFERT','AJUSTEMENT'], true)) {
            throw ValidationException::withMessages([
                'type_mvt' => 'Type mouvement invalide.'
            ]);
        }

        if (empty($p['emballage_id'])) {
            throw ValidationException::withMessages([
                'emballage_id' => 'emballage_id requis.'
            ]);
        }

        if (empty($p['code_lot'])) {
            throw ValidationException::withMessages([
                'code_lot' => 'code_lot requis.'
            ]);
        }

        if (!isset($p['quantite']) || (float)$p['quantite'] <= 0) {
            throw ValidationException::withMessages([
                'quantite' => 'Quantité doit être > 0.'
            ]);
        }

        if (empty($p['date_mvt'])) {
            throw ValidationException::withMessages([
                'date_mvt' => 'date_mvt requis.'
            ]);
        }

        if ($type === 'ENTREE' && empty($p['entrepot_dest_id'])) {
            throw ValidationException::withMessages([
                'entrepot_dest_id' => 'entrepot_dest_id requis pour ENTREE.'
            ]);
        }

        if ($type === 'SORTIE' && empty($p['entrepot_source_id'])) {
            throw ValidationException::withMessages([
                'entrepot_source_id' => 'entrepot_source_id requis pour SORTIE.'
            ]);
        }

        if ($type === 'TRANSFERT' &&
            (empty($p['entrepot_source_id']) || empty($p['entrepot_dest_id']))) {

            throw ValidationException::withMessages([
                'entrepot_source_id' => 'source requis pour TRANSFERT.',
                'entrepot_dest_id' => 'destination requis pour TRANSFERT.',
            ]);
        }
    }
}