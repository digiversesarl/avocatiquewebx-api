<?php

namespace App\Models;

use App\Models\Traits\ReferentielTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pays extends Model
{
    use HasFactory, ReferentielTrait;

    protected $table = 'pays';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'code',
        'label_fr',
        'label_ar',
        'label_en',
        'classement',
        'is_default',
        'is_active',
        'bg_color',
        'text_color',
    ];

    protected function casts(): array
    {
        return [
            'id'            => 'integer',
            'is_default' => 'boolean',
            'is_active'     => 'boolean',
            'classement'    => 'integer'
        ];
    }

    public function villes(): HasMany
    {
        return $this->hasMany(Ville::class, 'pays_id');
    }
}
