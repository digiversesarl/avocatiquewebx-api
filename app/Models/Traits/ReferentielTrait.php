<?php

namespace App\Models\Traits;

trait ReferentielTrait
{
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('classement');
    }
}
