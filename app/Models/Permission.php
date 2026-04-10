<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

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