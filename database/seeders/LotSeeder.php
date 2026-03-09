<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LotSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('lots')->insert([
            [
                'code_lot' => 'LOT-001',
                'emballage_id' => 1,
                'type_mvt' => 'ENTREE',
                'quantite' => 1000,
                'entrepot_source_id' => null,
                'entrepot_dest_id' => 1,
                'user_id' => 1,
                'date_mvt' => now(),
                'commentaire' => 'Réception fournisseur',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code_lot' => 'LOT-002',
                'emballage_id' => 2,
                'type_mvt' => 'ENTREE',
                'quantite' => 500,
                'entrepot_source_id' => null,
                'entrepot_dest_id' => 2,
                'user_id' => 1,
                'date_mvt' => now(),
                'commentaire' => 'Livraison initiale',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code_lot' => 'LOT-003',
                'emballage_id' => 3,
                'type_mvt' => 'SORTIE',
                'quantite' => 200,
                'entrepot_source_id' => 1,
                'entrepot_dest_id' => null,
                'user_id' => 1,
                'date_mvt' => now(),
                'commentaire' => 'Expédition client',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code_lot' => 'LOT-004',
                'emballage_id' => 4,
                'type_mvt' => 'TRANSFERT',
                'quantite' => 300,
                'entrepot_source_id' => 1,
                'entrepot_dest_id' => 3,
                'user_id' => 1,
                'date_mvt' => now(),
                'commentaire' => 'Transfert interne',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code_lot' => 'LOT-005',
                'emballage_id' => 5,
                'type_mvt' => 'AJUSTEMENT',
                'quantite' => 50,
                'entrepot_source_id' => 2,
                'entrepot_dest_id' => null,
                'user_id' => 1,
                'date_mvt' => now(),
                'commentaire' => 'Correction inventaire',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // duplication logique pour arriver à 10
        for ($i = 6; $i <= 10; $i++) {
            DB::table('lots')->insert([
                'code_lot' => 'LOT-00'.$i,
                'emballage_id' => $i,
                'type_mvt' => 'ENTREE',
                'quantite' => rand(100, 800),
                'entrepot_source_id' => null,
                'entrepot_dest_id' => rand(1, 5),
                'user_id' => 1,
                'date_mvt' => now(),
                'commentaire' => 'Entrée automatique',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}