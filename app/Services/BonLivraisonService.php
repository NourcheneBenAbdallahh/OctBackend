<?php

namespace App\Services;

use App\Models\BonLivraison;
use App\Models\Commande;
use App\Models\Entrepot;
use App\Models\Emballage;
use Illuminate\Support\Str;
use App\Models\Lot;
use App\Models\MouvementStock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use App\Services\StockService;

class BonLivraisonService
{   
    
public function __construct(
        private StockService $stockService 
    ) {}


    private const STATUTS = ['EN_ATTENTE', 'VALIDE'];

    public function create(array $data)
    {
        foreach (['date_reception', 'emballage_id', 'quantite_recue', 'numero_commande', 'entrepot_id'] as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("$field is required.");
            }
        }

        $entrepot = Entrepot::find($data['entrepot_id']);
        if (!$entrepot) {
            throw new \Exception("Entrepot with ID {$data['entrepot_id']} not found.");
        }

        $emballage = Emballage::find($data['emballage_id']);
        if (!$emballage) {
            throw new \Exception("Emballage with ID {$data['emballage_id']} not found.");
        }

        if (empty($data['numero_bl'])) {
            $data['numero_bl'] = 'BL-' . strtoupper(Str::random(8));
        }

        $data['statut'] = strtoupper($data['statut'] ?? 'EN_ATTENTE');
        if (!in_array($data['statut'], self::STATUTS, true)) {
            throw new \InvalidArgumentException("Invalid statut.");
        }

        $data['receptionne_par'] = 1;

        if ($data['quantite_recue'] <= 0) {
            throw new \InvalidArgumentException("quantite_recue must be > 0.");
        }

        $commande = Commande::where('numero_commande', $data['numero_commande'])->first();

        if (!$commande) {
            throw new \InvalidArgumentException("Commande not found.");
        }

        $data['commande_id'] = $commande->id;
        $data['quantite_recue'] = $commande->quantite;

        return BonLivraison::create($data);
    }

    public function update(BonLivraison $bonLivraison, array $data): BonLivraison
    {
        if ($bonLivraison->statut !== 'EN_ATTENTE') {
            throw new \InvalidArgumentException("Update allowed only if statut is EN_ATTENTE.");
        }

        $allowed = ['date_reception', 'emballage_id', 'quantite_recue', 'numero_commande', 'entrepot_id', 'statut'];
        $data = array_intersect_key($data, array_flip($allowed));

        if (isset($data['statut'])) {
            $data['statut'] = strtoupper($data['statut']);
            if (!in_array($data['statut'], self::STATUTS, true)) {
                throw new \InvalidArgumentException("Invalid statut.");
            }
        }

        if (isset($data['quantite_recue']) && $data['quantite_recue'] <= 0) {
            throw new \InvalidArgumentException("quantite_recue must be > 0.");
        }

        if (isset($data['numero_commande'])) {
            $commande = Commande::where('numero_commande', $data['numero_commande'])->first();

            if (!$commande) {
                throw new \InvalidArgumentException("Commande not found.");
            }

            $data['commande_id'] = $commande->id;
        }

        if (isset($data['entrepot_id'])) {
            $entrepot = Entrepot::find($data['entrepot_id']);
            if (!$entrepot) {
                throw new \InvalidArgumentException("Entrepot not found.");
            }
        }

        if (isset($data['emballage_id'])) {
            $emballage = Emballage::find($data['emballage_id']);
            if (!$emballage) {
                throw new \InvalidArgumentException("Emballage not found.");
            }
        }

        $bonLivraison->update($data);

        return $bonLivraison->refresh();
    }

    public function delete(BonLivraison $bonLivraison): BonLivraison
    {
        if ($bonLivraison->statut !== 'EN_ATTENTE') {
            throw new \InvalidArgumentException("Delete allowed only if statut is EN_ATTENTE.");
        }

        $bonLivraison->delete();
        return $bonLivraison;
    }

    public function validateBonLivraison(int $id, array $payload): BonLivraison
    {
        return DB::transaction(function () use ($id, $payload) {

            $bonLivraison = BonLivraison::findOrFail($id);

            if ($bonLivraison->statut === 'VALIDE') {
                throw ValidationException::withMessages([
                    'statut' => 'Ce bon de livraison est déjà validé.'
                ]);
            }

            if (empty($payload['document_bl'])) {
                throw ValidationException::withMessages([
                    'document_bl' => 'Le fichier du bon de livraison est obligatoire.'
                ]);
            }

            if (!$payload['document_bl'] instanceof UploadedFile) {
                throw ValidationException::withMessages([
                    'document_bl' => 'Le document BL doit être un fichier valide.'
                ]);
            }

            $documentPath = $payload['document_bl']->store('bon_livraisons', 'public');

            $bonLivraison->document_bl = $documentPath;
            $bonLivraison->statut = 'VALIDE';
            $bonLivraison->date_validation = now();
            $bonLivraison->validated_by = auth()->id();
            $bonLivraison->save();

            
            $lot = Lot::create([
                'code_lot' => $this->generateLotCode($bonLivraison),
                'emballage_id' => $bonLivraison->emballage_id,
                'quantite' => $bonLivraison->quantite_recue,
                'user_id' => auth()->id(),
                'date_mvt' => now(),
                'commentaire' => 'Lot généré automatiquement depuis validation du BL #' . $bonLivraison->id,
            ]);

            MouvementStock::create([
                'code_mouvement' => $this->generateMovementCode(),
                'type_mouvement' => 'EMC',
                'emballage_id' => $bonLivraison->emballage_id,
                'lot_id' => $lot->id,
                'entrepot_source_id' => null,
                'entrepot_destination_id' => $bonLivraison->entrepot_id,
                'quantite' => $bonLivraison->quantite_recue,
                'statut' => 'VALIDE',
            ]);

            $this->stockService->applyLotToStocks($lot, [
                'entrepot_id' => $bonLivraison->entrepot_id,
                'sens' => 'entree',
            ]);

            return $bonLivraison->fresh();
        });
    }

    private function generateLotCode(BonLivraison $bonLivraison): string
    {
        return 'LOT-' . now()->format('YmdHis') . '-' . $bonLivraison->id;
    }

    private function generateMovementCode(): string
    {
        return 'MVT-' . now()->format('YmdHis');
    }

}