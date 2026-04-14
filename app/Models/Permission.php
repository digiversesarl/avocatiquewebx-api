<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory, Auditable;

    public function auditCategory(): string
    {
        return 'roles';
    }

    /**
     * Table explicite
     */
    protected $table = 'permissions';

    /**
     * Champs autorisés
     */
    protected $fillable = [
        'name',
        'module',
        'action',
        'description',
    ];

    /**
     * Relation many-to-many avec Role
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permission',
            'permission_id',
            'role_id'
        );
    }
}