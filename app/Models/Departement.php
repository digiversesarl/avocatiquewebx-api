<?php

namespace App\Models;

use App\Models\Traits\ReferentielTrait;
use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Departement extends Model
{
    use HasFactory, ReferentielTrait, Auditable;

    protected $table = 'departements';

    protected $fillable = [
        'code',
        'label_fr',
        'label_ar',
        'label_en',
        'abbreviation',
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_departement');
    }
}
