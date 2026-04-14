<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'category',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'old_values',
        'new_values',
        'result',
        'ip_address',
        'user_agent',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeResult($query, string $result)
    {
        return $query->where('result', $result);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // ── Helper statique pour logger facilement ────────────────────

    /**
     * Log un événement d'audit.
     */
    public static function log(
        string $action,
        string $category = 'data',
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $result = 'success',
        ?string $description = null,
        ?Model $actingUser = null,
        ?string $label = null
    ): self {
        $user = $actingUser ?? auth()->user();
        $request = request();

        $userName = 'Système';
        if ($user) {
            $userName = $user->full_name_fr ?: ($user->login ?: $user->email);
        }

        return self::create([
            'user_id'         => $user ? $user->id : null,
            'user_name'       => $userName,
            'action'          => $action,
            'category'        => $category,
            'auditable_type'  => $auditable ? get_class($auditable) : null,
            'auditable_id'    => $auditable ? $auditable->getKey() : null,
            'auditable_label' => $auditable ? self::resolveLabel($auditable) : $label,
            'old_values'      => $oldValues,
            'new_values'      => $newValues,
            'result'          => $result,
            'ip_address'      => $request ? $request->ip() : null,
            'user_agent'      => $request ? $request->userAgent() : null,
            'description'     => $description,
        ]);
    }

    /**
     * Résout un label lisible pour n'importe quel modèle auditable.
     */
    private static function resolveLabel(Model $model): string
    {
        // Priorité aux champs courants
        foreach (['label_fr', 'full_name_fr', 'name', 'firm_name_fr', 'login', 'email', 'label', 'slug', 'title'] as $field) {
            if (!empty($model->$field)) {
                return (string) $model->$field;
            }
        }

        return class_basename($model) . ' #' . $model->getKey();
    }

    // ── Helpers spécialisés ───────────────────────────────────────

    /**
     * Audite une opération sync() sur une relation BelongsToMany.
     *
     * Compare les IDs actuels avec les nouveaux, log les ajouts/retraits,
     * puis exécute le sync().
     *
     * @param  Model   $parent     Le modèle parent (User, Role, MenuItem…)
     * @param  string  $relation   Nom de la relation ('roles', 'permissions', 'groupes'…)
     * @param  array   $newIds     Les nouveaux IDs à synchroniser
     * @param  string  $action     Action d'audit ('role_granted', 'settings_changed'…)
     * @param  string  $category   Catégorie ('roles', 'data', 'settings'…)
     * @return array               Résultat du sync() (attached, detached, updated)
     */
    public static function auditSync(
        Model $parent,
        string $relation,
        array $newIds,
        string $action = 'record_updated',
        string $category = 'data'
    ): array {
        $currentIds = $parent->$relation()->pluck('id')->toArray();

        $added   = array_values(array_diff($newIds, $currentIds));
        $removed = array_values(array_diff($currentIds, $newIds));

        $result = $parent->$relation()->sync($newIds);

        if (!empty($added) || !empty($removed)) {
            self::log(
                $action,
                $category,
                $parent,
                !empty($removed) ? [$relation . '_removed' => $removed] : null,
                !empty($added)   ? [$relation . '_added'   => $added]   : null,
                'success',
                class_basename($parent) . " — {$relation} synchronisés"
            );
        }

        return $result;
    }

    /**
     * Audite une opération de réordonnancement (reorder).
     *
     * Log un seul événement résumant tous les changements de classement.
     *
     * @param  string  $modelClass  FQCN du modèle (Pays::class, MenuItem::class…)
     * @param  array   $items       Tableau [['id' => x, 'classement' => y], …]
     * @param  string  $field       Nom du champ d'ordre ('classement', 'ordre')
     */
    public static function auditReorder(string $modelClass, array $items, string $field = 'classement'): void
    {
        $ids = array_column($items, 'id');
        $models = $modelClass::whereIn('id', $ids)->pluck($field, 'id')->toArray();

        $changes = [];
        foreach ($items as $item) {
            $oldVal = $models[$item['id']] ?? null;
            $newVal = $item[$field] ?? $item['classement'] ?? $item['ordre'] ?? null;
            if ($oldVal !== null && (int) $oldVal !== (int) $newVal) {
                $changes[$item['id']] = ['old' => (int) $oldVal, 'new' => (int) $newVal];
            }
        }

        // Exécuter les updates
        foreach ($items as $item) {
            $modelClass::where('id', $item['id'])->update([$field => $item[$field] ?? $item['classement'] ?? $item['ordre']]);
        }

        // Logger si des changements réels
        if (!empty($changes)) {
            self::log(
                'record_updated',
                'data',
                null,
                ['reorder_before' => $changes],
                ['model' => class_basename($modelClass), 'count' => count($changes)],
                'success',
                class_basename($modelClass) . ' — réordonnancement (' . count($changes) . ' éléments)'
            );
        }
    }
}
