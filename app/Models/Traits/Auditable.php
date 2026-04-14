<?php

namespace App\Models\Traits;

use App\Models\AuditLog;

/**
 * Trait Auditable
 *
 * Ajoute l'audit automatique (created, updated, deleted) à un modèle Eloquent.
 * Utiliser `use Auditable;` dans le modèle.
 *
 * Les champs sensibles (password, tfa_secret, remember_token) sont masqués.
 *
 * Hooks personnalisables par modèle :
 *   - auditCategory(): string          → catégorie du log (default: 'data')
 *   - auditExcludedFields(): array     → champs supplémentaires à exclure
 *   - auditDescriptionCreated(): string
 *   - auditDescriptionUpdated(): string
 *   - auditDescriptionDeleted(): string
 */
trait Auditable
{
    /**
     * Champs globaux à exclure de l'audit (données sensibles / timestamps).
     */
    protected static array $auditExcluded = [
        'password', 'remember_token', 'tfa_secret', 'updated_at', 'created_at',
    ];

    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $newValues = self::filterAuditValues($model->getAttributes(), $model);

            AuditLog::log(
                'record_created',
                $model->auditCategory(),
                $model,
                null,
                $newValues,
                'success',
                $model->auditDescriptionCreated()
            );
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            if (empty($dirty)) {
                return;
            }

            $excluded = array_merge(self::$auditExcluded, $model->auditExcludedFields());

            $oldValues = [];
            $newValues = [];
            foreach ($dirty as $key => $newValue) {
                if (in_array($key, $excluded)) {
                    continue;
                }
                $oldValues[$key] = $model->getOriginal($key);
                $newValues[$key] = $newValue;
            }

            if (empty($newValues)) {
                return;
            }

            AuditLog::log(
                'record_updated',
                $model->auditCategory(),
                $model,
                $oldValues,
                $newValues,
                'success',
                $model->auditDescriptionUpdated()
            );
        });

        static::deleted(function ($model) {
            $oldValues = self::filterAuditValues($model->getAttributes(), $model);

            AuditLog::log(
                'record_deleted',
                $model->auditCategory(),
                $model,
                $oldValues,
                null,
                'success',
                $model->auditDescriptionDeleted()
            );
        });
    }

    // ── Hooks personnalisables ──────────────────────────────────

    /**
     * Catégorie du log d'audit. Surcharger dans le modèle si besoin.
     * Valeurs possibles : 'data', 'auth', 'roles', 'security', 'settings'
     */
    public function auditCategory(): string
    {
        return 'data';
    }

    /**
     * Champs supplémentaires à exclure (en plus des globaux).
     */
    public function auditExcludedFields(): array
    {
        return [];
    }

    /**
     * Description pour la création.
     */
    public function auditDescriptionCreated(): string
    {
        return class_basename($this) . ' créé';
    }

    /**
     * Description pour la modification.
     */
    public function auditDescriptionUpdated(): string
    {
        return class_basename($this) . ' modifié';
    }

    /**
     * Description pour la suppression.
     */
    public function auditDescriptionDeleted(): string
    {
        return class_basename($this) . ' supprimé';
    }

    // ── Filtrage ────────────────────────────────────────────────

    /**
     * Filtre les valeurs pour exclure les champs sensibles.
     */
    private static function filterAuditValues(array $values, $model = null): array
    {
        $excluded = self::$auditExcluded;
        if ($model && method_exists($model, 'auditExcludedFields')) {
            $excluded = array_merge($excluded, $model->auditExcludedFields());
        }

        return array_diff_key($values, array_flip($excluded));
    }
}
