<?php

namespace App\Models;

use App\Models\Traits\ReferentielTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ville extends Model
{
    use HasFactory, ReferentielTrait;

    protected $table = 'villes';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'id',
        'label_fr',
        'label_ar',
        'label_en',
        'abbreviation',
        'pays_id',
        'classement',
        'is_default',
        'is_active',
        'bg_color',
        'text_color',
    ];

    protected function casts(): array
    {
        return [
            'id'         => 'integer',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'classement' => 'integer',
            'pays_id'    => 'integer',
        ];
    }

    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class, 'pays_id');
    }
}
