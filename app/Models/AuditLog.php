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
        ?string $description = null
    ): self {
        $user = auth()->user();
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
            'auditable_label' => $auditable ? self::resolveLabel($auditable) : null,
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
}
