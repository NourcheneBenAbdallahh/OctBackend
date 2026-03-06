<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fournisseur extends Model
{
    // Activez SoftDeletes uniquement si votre table a deleted_at
    // use SoftDeletes;

    protected $table = 'fournisseurs';

    protected $fillable = [
        'raison_sociale',
        'matricule_fiscale',
        'telephone',
        'adresse',
        'statut',
    ];

    public function contrats()
{
    return $this->hasMany(Contrat::class, 'fournisseur_id');
}
}