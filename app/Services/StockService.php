<?php

namespace App\Services;

use App\Models\Lot;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    public function applyLotToStocks(Lot $lot): array
    {
        $ts = Carbon::parse($lot->date_mvt);
        $stocks = [];

        switch ($lot->type_mvt) {

            case 'ENTREE':

                $stocks[] = $this->applySnapshot(
                    $lot->entrepot_dest_id,
                    $lot->emballage_id,
                    $ts,
                    $lot->id,
                    $lot->user_id,
                    $lot->quantite,
                    0
                );

                break;

            case 'SORTIE':

                $stocks[] = $this->applySnapshot(
                    $lot->entrepot_source_id,
                    $lot->emballage_id,
                    $ts,
                    $lot->id,
                    $lot->user_id,
                    0,
                    $lot->quantite
                );

                break;

            case 'TRANSFERT':

                $stocks[] = $this->applySnapshot(
                    $lot->entrepot_source_id,
                    $lot->emballage_id,
                    $ts,
                    $lot->id,
                    $lot->user_id,
                    0,
                    $lot->quantite
                );

                $stocks[] = $this->applySnapshot(
                    $lot->entrepot_dest_id,
                    $lot->emballage_id,
                    $ts,
                    $lot->id,
                    $lot->user_id,
                    $lot->quantite,
                    0
                );

                break;

            case 'AJUSTEMENT':

                $entrepot = $lot->entrepot_dest_id ?? $lot->entrepot_source_id;

                if (!$entrepot) {
                    throw new RuntimeException("AJUSTEMENT requiert un entrepot.");
                }

                if ($lot->quantite >= 0) {
                    $stocks[] = $this->applySnapshot(
                        $entrepot,
                        $lot->emballage_id,
                        $ts,
                        $lot->id,
                        $lot->user_id,
                        $lot->quantite,
                        0
                    );
                } else {
                    $stocks[] = $this->applySnapshot(
                        $entrepot,
                        $lot->emballage_id,
                        $ts,
                        $lot->id,
                        $lot->user_id,
                        0,
                        abs($lot->quantite)
                    );
                }

                break;

            default:
                throw new RuntimeException("type_mvt non supporté");
        }

        return $stocks;
    }

    private function applySnapshot(
        int $entrepotId,
        int $emballageId,
        Carbon $at,
        int $lotId,
        ?int $userId,
        float $entree,
        float $sortie
    ): Stock {

        return DB::transaction(function () use (
            $entrepotId,
            $emballageId,
            $at,
            $lotId,
            $userId,
            $entree,
            $sortie
        ) {

            $lastFinale = Stock::where('entrepot_id', $entrepotId)
                ->where('emballage_id', $emballageId)
                ->where('date_stock', '<=', $at)
                ->orderByDesc('date_stock')
                ->lockForUpdate()
                ->value('quantite_finale');

            $init = $lastFinale ? (float) $lastFinale : 0;

            $finale = $init + $entree - $sortie;

            if ($finale < 0) {
                throw new RuntimeException("Stock insuffisant.");
            }

            return Stock::create([
                'entrepot_id' => $entrepotId,
                'emballage_id' => $emballageId,
                'lot_id' => $lotId,
                'date_stock' => $at,
                'quantite_init' => $init,
                'quantite_entree' => $entree,
                'quantite_sortie' => $sortie,
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
            ->value('quantite_finale');

        return $finale ? (float) $finale : 0;
    }
}