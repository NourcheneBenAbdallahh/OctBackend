<?php

namespace App\Services;

use App\Models\Lot;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    public function applyLotToStocks(Lot $lot, array $context = []): array
    {
        $entrepotId = $context['entrepot_id'] ?? null;
        $sens = $context['sens'] ?? 'entree';

        if (!$entrepotId) {
            throw new RuntimeException("entrepot_id est requis pour créer le mouvement de stock.");
        }

        if (!in_array($sens, ['entree', 'sortie'], true)) {
            throw new RuntimeException("sens invalide. Valeurs autorisées : entree, sortie.");
        }

        $ts = Carbon::parse($lot->date_mvt);

        $stock = $this->applySnapshot(
            $entrepotId,
            $lot->emballage_id,
            $ts,
            $lot->id,
            $lot->user_id,
            (float) $lot->quantite,
            $sens
        );

        return [$stock];
    }

    private function applySnapshot(
        int $entrepotId,
        int $emballageId,
        Carbon $at,
        int $lotId,
        ?int $userId,
        float $qte,
        string $sens
    ): Stock {
        return DB::transaction(function () use (
            $entrepotId,
            $emballageId,
            $at,
            $lotId,
            $userId,
            $qte,
            $sens
        ) {
            $lastFinale = Stock::where('entrepot_id', $entrepotId)
                ->where('emballage_id', $emballageId)
                ->where('date_stock', '<=', $at)
                ->orderByDesc('date_stock')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('quantite_finale');

            $init = $lastFinale ? (float) $lastFinale : 0;

            $finale = match ($sens) {
                'entree' => $init + $qte,
                'sortie' => $init - $qte,
                default => throw new RuntimeException("sens non supporté"),
            };

            if ($finale < 0) {
                throw new RuntimeException("Stock insuffisant.");
            }

            return Stock::create([
                'entrepot_id' => $entrepotId,
                'emballage_id' => $emballageId,
                'lot_id' => $lotId,
                'date_stock' => $at,
                'quantite_init' => $init,
                'qte' => $qte,
                'sens' => $sens,
                'quantite_finale' => $finale,
                'user_id' => $userId,
            ]);
        });
    }

    public function getTheoriqueAt(int $entrepotId, int $emballageId, string $dateTime): float
    {
        $dt = Carbon::parse($dateTime);

        $finale = Stock::where('entrepot_id', $entrepotId)
            ->where('emballage_id', $emballageId)
            ->where('date_stock', '<=', $dt)
            ->orderByDesc('date_stock')
            ->orderByDesc('id')
            ->value('quantite_finale');

        return $finale ? (float) $finale : 0;
    }

    public function updateStockFromLotChange(Lot $oldLot, Lot $newLot, array $context = []): void
    {
        $this->deleteStocksByLot($oldLot);
        $this->applyLotToStocks($newLot, $context);

        $entrepotId = $context['entrepot_id'] ?? null;
        if ($entrepotId) {
            $oldDate = Carbon::parse($oldLot->date_mvt);
            $newDate = Carbon::parse($newLot->date_mvt);
            $rebuildFrom = $oldDate->lt($newDate) ? $oldDate : $newDate;

            $this->rebuildStockTimeline(
                $entrepotId,
                $newLot->emballage_id,
                $rebuildFrom
            );
        }
    }

    public function removeStocksFromLot(Lot $lot): void
    {
        $this->deleteStocksByLot($lot);
    }

    public function getImpactedScopes(Lot $lot, array $context = []): array
    {
        $scopes = Stock::where('lot_id', $lot->id)
            ->get(['entrepot_id', 'emballage_id'])
            ->map(fn ($stock) => [
                'entrepot_id' => $stock->entrepot_id,
                'emballage_id' => $stock->emballage_id,
            ])
            ->unique(fn ($s) => $s['entrepot_id'] . '-' . $s['emballage_id'])
            ->values()
            ->all();

        if (!empty($scopes)) {
            return $scopes;
        }

        if (!empty($context['entrepot_id'])) {
            return [[
                'entrepot_id' => $context['entrepot_id'],
                'emballage_id' => $lot->emballage_id,
            ]];
        }

        return [];
    }

    public function rebuildStockTimeline(int $entrepotId, int $emballageId, Carbon $fromDate): void
    {
        DB::transaction(function () use ($entrepotId, $emballageId, $fromDate) {
            $previousStock = Stock::where('entrepot_id', $entrepotId)
                ->where('emballage_id', $emballageId)
                ->where('date_stock', '<', $fromDate)
                ->orderBy('date_stock', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $runningFinale = $previousStock ? (float) $previousStock->quantite_finale : 0;

            $stocks = Stock::where('entrepot_id', $entrepotId)
                ->where('emballage_id', $emballageId)
                ->where('date_stock', '>=', $fromDate)
                ->orderBy('date_stock', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($stocks as $stock) {
                $stock->quantite_init = $runningFinale;

                $stock->quantite_finale = match ($stock->sens) {
                    'entree' => $runningFinale + (float) $stock->qte,
                    'sortie' => $runningFinale - (float) $stock->qte,
                    default => throw new RuntimeException("sens invalide lors du recalcul."),
                };

                if ($stock->quantite_finale < 0) {
                    throw new RuntimeException("Stock insuffisant lors du recalcul.");
                }

                $stock->save();

                $runningFinale = (float) $stock->quantite_finale;
            }
        });
    }

    public function deleteStocksByLot(Lot $lot): void
    {
        Stock::where('lot_id', $lot->id)->delete();
    }
}