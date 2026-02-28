<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Packaging extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'capacity_value',
        'capacity_unit',
        'material',
        'status',
    ];
}