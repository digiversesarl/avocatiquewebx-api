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
 */
trait Auditable
{
    /**
     * Champs à exclure de l'audit (données sensibles).
     */
    protected static array $auditExcluded = [
        'password', 'remember_token', 'tfa_secret', 'updated_at', 'created_at',
    ];

    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $newValues = self::filterAuditValues($model->getAttributes());

            AuditLog::log(
                'record_created',        // action
                'data',                  // category
                $model,                  // auditable
                null,                    // oldValues
                $newValues,              // newValues
                'success',               // result
                class_basename($model) . ' créé'
            );
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            if (empty($dirty)) {
                return;
            }

            $oldValues = [];
            $newValues = [];
            foreach ($dirty as $key => $newValue) {
                if (in_array($key, self::$auditExcluded)) {
                    continue;
                }
                $oldValues[$key] = $model->getOriginal($key);
                $newValues[$key] = $newValue;
            }

            if (empty($newValues)) {
                return;
            }

            AuditLog::log(
                'record_updated',        // action
                'data',                  // category
                $model,                  // auditable
                $oldValues,              // oldValues
                $newValues,              // newValues
                'success',               // result
                class_basename($model) . ' modifié'
            );
        });

        static::deleted(function ($model) {
            $oldValues = self::filterAuditValues($model->getAttributes());

            AuditLog::log(
                'record_deleted',        // action
                'data',                  // category
                $model,                  // auditable
                $oldValues,              // oldValues
                null,                    // newValues
                'success',               // result
                class_basename($model) . ' supprimé'
            );
        });
    }

    /**
     * Filtre les valeurs pour exclure les champs sensibles.
     */
    private static function filterAuditValues(array $values): array
    {
        return array_diff_key($values, array_flip(self::$auditExcluded));
    }
}
