<?php

namespace App\Services;

use App\Models\Lot;
use App\Models\LotHistorique;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

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
                'code_lot'     => $payload['code_lot'],
                'emballage_id' => (int) $payload['emballage_id'],
                'quantite'     => (float) $payload['quantite'],
                'user_id'      => $payload['user_id'] ?? null,
                'date_mvt'     => $payload['date_mvt'],
                'commentaire'  => $payload['commentaire'] ?? null,
            ]);

            // Appliquer le lot dans le stock
            $this->stockService->applyLotToStocks($lot, $payload);

            return $lot;
        });
    }

    public function findLot(int $id): Lot
    {
        $lot = Lot::with(['emballage', 'user'])->find($id);

        if (!$lot) {
            throw ValidationException::withMessages([
                'id' => "Lot #$id introuvable."
            ]);
        }

        return $lot;
    }

    public function listLots(int $perPage = 10)
    {
        return Lot::with(['emballage', 'user'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function updateLot(int $id, array $payload): Lot
    {
        return DB::transaction(function () use ($id, $payload) {
            $lot = $this->findLot($id);
            $oldLot = clone $lot;
            $oldDate = Carbon::parse($oldLot->date_mvt);

            $lot->fill([
                'code_lot'     => $payload['code_lot'] ?? $lot->code_lot,
                'emballage_id' => $payload['emballage_id'] ?? $lot->emballage_id,
                'quantite'     => $payload['quantite'] ?? $lot->quantite,
                'user_id'      => $payload['user_id'] ?? $lot->user_id,
                'date_mvt'     => $payload['date_mvt'] ?? $lot->date_mvt,
                'commentaire'  => $payload['commentaire'] ?? $lot->commentaire,
            ]);

            $lot->save();

            // Supprimer anciens mouvements stock liés à ce lot
            $this->stockService->deleteStocksByLot($oldLot);

            // Recréer les nouveaux mouvements stock
            $this->stockService->applyLotToStocks($lot, $payload);

            $newDate = Carbon::parse($lot->date_mvt);

            $scopes = array_merge(
                $this->stockService->getImpactedScopes($oldLot),
                $this->stockService->getImpactedScopes($lot, $payload)
            );

            $uniqueScopes = collect($scopes)
                ->unique(fn ($s) => $s['entrepot_id'] . '-' . $s['emballage_id'])
                ->values();

            foreach ($uniqueScopes as $scope) {
                $rebuildFrom = $oldDate->lt($newDate) ? $oldDate : $newDate;

                $this->stockService->rebuildStockTimeline(
                    $scope['entrepot_id'],
                    $scope['emballage_id'],
                    $rebuildFrom
                );
            }

            return $lot;
        });
    }

    public function deleteLot(int $id): Lot
    {
        return DB::transaction(function () use ($id) {
            $lot = $this->findLot($id);
            $date = Carbon::parse($lot->date_mvt);

            $scopes = $this->stockService->getImpactedScopes($lot);

            $this->stockService->deleteStocksByLot($lot);

            $lot->delete();

            $uniqueScopes = collect($scopes)
                ->unique(fn ($s) => $s['entrepot_id'] . '-' . $s['emballage_id'])
                ->values();

            foreach ($uniqueScopes as $scope) {
                $this->stockService->rebuildStockTimeline(
                    $scope['entrepot_id'],
                    $scope['emballage_id'],
                    $date
                );
            }

            return $lot;
        });
    }

    public function updateLotWithHistory(int $id, array $input): Lot
    {
        return DB::transaction(function () use ($id, $input) {
            $lot = Lot::findOrFail($id);

            $oldLot = clone $lot;
            $oldQuantite = $lot->quantite;
            $oldDate = Carbon::parse($oldLot->date_mvt);

            $this->stockService->deleteStocksByLot($oldLot);

            $lot->update([
                'code_lot'     => $input['code_lot'] ?? $lot->code_lot,
                'emballage_id' => $input['emballage_id'] ?? $lot->emballage_id,
                'quantite'     => $input['quantite'] ?? $lot->quantite,
                'user_id'      => $input['user_id'] ?? $lot->user_id,
                'date_mvt'     => $input['date_mvt'] ?? $lot->date_mvt,
                'commentaire'  => $input['commentaire'] ?? $lot->commentaire,
            ]);

            $this->stockService->applyLotToStocks($lot, $input);

            $newDate = Carbon::parse($lot->date_mvt);

            $scopes = array_merge(
                $this->stockService->getImpactedScopes($oldLot),
                $this->stockService->getImpactedScopes($lot, $input)
            );

            $uniqueScopes = collect($scopes)
                ->unique(fn ($s) => $s['entrepot_id'] . '-' . $s['emballage_id'])
                ->values();

            foreach ($uniqueScopes as $scope) {
                $rebuildFrom = $oldDate->lt($newDate) ? $oldDate : $newDate;

                $this->stockService->rebuildStockTimeline(
                    $scope['entrepot_id'],
                    $scope['emballage_id'],
                    $rebuildFrom
                );
            }

            LotHistorique::create([
                'lot_id'              => $lot->id,
                'ancienne_quantite'   => $oldQuantite,
                'nouvelle_quantite'   => $lot->quantite,
                'user_id'             => auth()->id(),
                'date_modification'   => now(),
            ]);

            return $lot;
        });
    }

    private function validatePayload(array $p): void
    {
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

        if (!isset($p['quantite']) || (float) $p['quantite'] <= 0) {
            throw ValidationException::withMessages([
                'quantite' => 'Quantité doit être > 0.'
            ]);
        }

        if (empty($p['date_mvt'])) {
            throw ValidationException::withMessages([
                'date_mvt' => 'date_mvt requis.'
            ]);
        }
    }
}