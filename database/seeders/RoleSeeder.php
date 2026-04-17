<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Création des rôles (ID + level)
        |--------------------------------------------------------------------------
        */

        $roles = [
            ['id' => 1, 'name' => 'superadmin', 'level' => 200, 'color' => '#ef4444'],
            ['id' => 2, 'name' => 'admin',      'level' => 199, 'color' => '#3b82f6'],
            ['id' => 3, 'name' => 'avocat',     'level' => 198, 'color' => '#f59e0b'],
            ['id' => 4, 'name' => 'secretaire', 'level' => 197, 'color' => '#22c55e'],
            ['id' => 5, 'name' => 'stagiaire',  'level' => 196, 'color' => '#a855f7'],
            ['id' => 6, 'name' => 'agent',      'level' => 195, 'color' => '#64748b'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['id' => $role['id']],
                [
                    'name'  => $role['name'],
                    'level' => $role['level'],
                    'color' => $role['color'],
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Assignation des permissions
        |--------------------------------------------------------------------------
        */

        $allPerms      = Permission::pluck('id');
        $nonAdminPerms = Permission::where('module', '!=', 'admin')->pluck('id');
        $nonAdminNonRefPerms = Permission::where('module', '!=', 'admin')
            ->where('module', 'not like', 'ref_%')
            ->where('module', '!=', 'users')
            ->where('module', '!=', 'translations')
            ->where('module', '!=', 'menu')
            ->pluck('id');
		
		
		// ── Super Admin : TOUT ───────────────────────────────────────────
        $superadmin = Role::where('name', 'superadmin')->first();
        $superadmin?->permissions()->sync($allPerms);


        // ── Admin ───────────────────────────────
        $admin = Role::where('name', 'admin')->first();
        $admin?->permissions()->sync($allPerms);

        // ── Avocat ──────────────────────────────
        $avocat = Role::where('name', 'avocat')->first();
        $avocat?->permissions()->sync($nonAdminNonRefPerms);

        // ── Secrétaire ──────────────────────────
        $secretaire = Role::where('name', 'secretaire')->first();
        $secretaire?->permissions()->sync(
            Permission::whereIn('name', [
                'affaires.view',
                'clients.view',
                'clients.create',
                'calendrier.view',
                'calendrier.manage',
                'documents.view',
                'documents.upload',
            ])->pluck('id')
        );

        // ── Stagiaire ───────────────────────────
        $stagiaire = Role::where('name', 'stagiaire')->first();
        $stagiaire?->permissions()->sync(
            Permission::whereIn('name', [
                'affaires.view',
                'clients.view',
                'calendrier.view',
                'documents.view',
            ])->pluck('id')
        );
    }
}