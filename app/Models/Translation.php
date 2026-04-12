<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $fillable = [
        'code',
        'libelle_fr',
        'libelle_ar',
        'libelle_en',
    ];
}
