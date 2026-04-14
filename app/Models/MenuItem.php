<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\Auditable;

class MenuItem extends Model
{
    use Auditable;
    protected $fillable = [
        'parent_id',
        'label_fr',
        'label_ar',
        'label_en',
        'icon',
        'route',
        'ordre',
        'is_visible',
        'module',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'ordre'      => 'integer',
            'parent_id'  => 'integer',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('ordre');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'menu_item_role');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
