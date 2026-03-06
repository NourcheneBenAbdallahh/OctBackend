<?php

namespace App\Services;

use App\Models\Lot;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    public function applyLotToStockJournal(Lot $lot): void
    {
        $ts = Carbon::parse($lot->date_mvt); // datetime exact

        match ($lot->type_mvt) {
            'ENTREE' => $this->applySnapshot(
                entrepotId: $lot->entrepot_dest_id,
                emballageId: $lot->emballage_id,
                at: $ts,
                lotId: $lot->id,
                userId: $lot->user_id,
                entree: (float)$lot->quantite,
                sortie: 0.0
            ),
            'SORTIE' => $this->applySnapshot(
                entrepotId: $lot->entrepot_source_id,
                emballageId: $lot->emballage_id,
                at: $ts,
                lotId: $lot->id,
                userId: $lot->user_id,
                entree: 0.0,
                sortie: (float)$lot->quantite
            ),
            'TRANSFERT' => $this->applyTransfer($lot, $ts),
            'AJUSTEMENT' => $this->applyAjustement($lot, $ts),
            default => throw new RuntimeException("type_mvt non supporté: {$lot->type_mvt}")
        };
    }

    private function applyTransfer(Lot $lot, Carbon $ts): void
    {
        // sortie dépôt source
        $this->applySnapshot(
            entrepotId: $lot->entrepot_source_id,
            emballageId: $lot->emballage_id,
            at: $ts,
            lotId: $lot->id,
            userId: $lot->user_id,
            entree: 0.0,
            sortie: (float)$lot->quantite
        );

        // entrée dépôt destination
        $this->applySnapshot(
            entrepotId: $lot->entrepot_dest_id,
            emballageId: $lot->emballage_id,
            at: $ts,
            lotId: $lot->id,
            userId: $lot->user_id,
            entree: (float)$lot->quantite,
            sortie: 0.0
        );
    }

    private function applyAjustement(Lot $lot, Carbon $ts): void
    {
        // Reco: si tu veux ajustement +/- : quantite peut être négative.
        $q = (float)$lot->quantite;

        $entrepot = $lot->entrepot_dest_id ?? $lot->entrepot_source_id;
        if (!$entrepot) {
            throw new RuntimeException("AJUSTEMENT requiert entrepot_source_id ou entrepot_dest_id.");
        }

        if ($q >= 0) {
            $this->applySnapshot($entrepot, $lot->emballage_id, $ts, $lot->id, $lot->user_id, $q, 0.0);
        } else {
            $this->applySnapshot($entrepot, $lot->emballage_id, $ts, $lot->id, $lot->user_id, 0.0, abs($q));
        }
    }

    private function applySnapshot(
        int $entrepotId,
        int $emballageId,
        Carbon $at,
        int $lotId,
        ?int $userId,
        float $entree,
        float $sortie
    ): void {
        DB::transaction(function () use ($entrepotId, $emballageId, $at, $lotId, $userId, $entree, $sortie) {

            // 1) Lock du dernier état (anti concurrence)
            $lastFinale = Stock::where('entrepot_id', $entrepotId)
                ->where('emballage_id', $emballageId)
                ->where('date_stock', '<', $at->toDateTimeString())
                ->orderBy('date_stock', 'desc')
                ->lockForUpdate()
                ->value('quantite_finale');

            $init = $lastFinale ? (float)$lastFinale : 0.0;

            $finale = $init + $entree - $sortie;

            if ($finale < 0) {
                throw new RuntimeException("Stock insuffisant (finale={$finale}).");
            }

            // 2) INSERT uniquement (append-only)
            Stock::create([
                'entrepot_id'     => $entrepotId,
                'emballage_id'    => $emballageId,
                'lot_id'          => $lotId,
                'date_stock'      => $at->toDateTimeString(),
                'quantite_init'   => $init,
                'quantite_entree' => $entree,
                'quantite_sortie' => $sortie,
                'quantite_finale' => $finale,
                'user_id'         => $userId,
            ]);
        });
    }

    public function getTheoriqueAt(int $entrepotId, int $emballageId, string $dateTime): float
    {
        $dt = Carbon::parse($dateTime)->toDateTimeString();

        $finale = Stock::where('entrepot_id', $entrepotId)
            ->where('emballage_id', $emballageId)
            ->where('date_stock', '<=', $dt)
            ->orderBy('date_stock', 'desc')
            ->value('quantite_finale');

        return $finale ? (float)$finale : 0.0;
    }
}