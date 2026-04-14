<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    use Auditable;

    public function auditCategory(): string
    {
        return 'settings';
    }

    protected $fillable = [
        'code',
        'libelle_fr',
        'libelle_ar',
        'libelle_en',
    ];
}
