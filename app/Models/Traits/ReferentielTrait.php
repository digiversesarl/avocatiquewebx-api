<?php

namespace App\Models\Traits;

trait ReferentielTrait
{
    use Auditable;

    /**
     * Catégorie d'audit pour les référentiels.
     */
    public function auditCategory(): string
    {
        return 'referentiel';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('classement');
    }
}
