<?php

namespace App\Models;

use App\Models\Traits\ReferentielTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fonction extends Model
{
    use HasFactory, ReferentielTrait;

    protected $table = 'fonctions';

    protected $fillable = [
        'id',
        'code',
        'label_fr',
        'label_ar',
        'label_en',
        'classement',
        'is_active',
        'is_default',
        'bg_color',
        'text_color',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_default' => 'boolean',
            'classement' => 'integer',
            'id'     => 'integer',
        ];
    }
}
