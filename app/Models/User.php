<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, Auditable;

    protected $fillable = [
        'matricule', 'email', 'full_name_fr', 'full_name_ar',
        'abbreviation_fr', 'abbreviation_ar', 'photo',
        'fonction', 'grade_avocat', 'departement', 'avocat_proprietaire',
        'address_fr', 'address_ar', 'telephone',
        'langue', 'rib', 'cin', 'date_entree',
        'valeur_par_defaut', 'classement', 'active',
        'couleur_fond', 'couleur_texte',
        'tarif_journalier', 'observation',
        'login', 'password', 'is_admin',
        'tfa_enabled', 'tfa_secret', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token', 'tfa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'   => 'datetime',
            'date_entree'         => 'date',
            'password'            => 'hashed',
            'active'              => 'boolean',
            'is_admin'            => 'boolean',
            'valeur_par_defaut'   => 'boolean',
            'avocat_proprietaire' => 'boolean',
            'tfa_enabled'         => 'boolean',
            'tarif_journalier'    => 'decimal:2',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function groupes(): BelongsToMany
    {
        return $this->belongsToMany(Groupe::class, 'user_groupe');
    }

    public function departements(): BelongsToMany
    {
        return $this->belongsToMany(Departement::class, 'user_departement');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(UserAttachment::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('name', $permission))
            ->exists();
    }

    public function allPermissions(): array
    {
        return $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()->sort()->values()->all();
    }
}
