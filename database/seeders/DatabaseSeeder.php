<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Permissions ─────────────────────────────────────────────────
        $permissionsData = [
            // Affaires
            ['name' => 'affaires.view',      'module' => 'affaires',       'action' => 'view'],
            ['name' => 'affaires.create',    'module' => 'affaires',       'action' => 'create'],
            ['name' => 'affaires.edit',      'module' => 'affaires',       'action' => 'edit'],
            ['name' => 'affaires.delete',    'module' => 'affaires',       'action' => 'delete'],
            // Clients
            ['name' => 'clients.view',       'module' => 'clients',        'action' => 'view'],
            ['name' => 'clients.create',     'module' => 'clients',        'action' => 'create'],
            ['name' => 'clients.edit',       'module' => 'clients',        'action' => 'edit'],
            ['name' => 'clients.delete',     'module' => 'clients',        'action' => 'delete'],
            // Calendrier
            ['name' => 'calendrier.view',    'module' => 'calendrier',     'action' => 'view'],
            ['name' => 'calendrier.manage',  'module' => 'calendrier',     'action' => 'manage'],
            // Documents
            ['name' => 'documents.view',     'module' => 'documents',      'action' => 'view'],
            ['name' => 'documents.upload',   'module' => 'documents',      'action' => 'upload'],
            ['name' => 'documents.delete',   'module' => 'documents',      'action' => 'delete'],
            // Facturation
            ['name' => 'facturation.view',   'module' => 'facturation',    'action' => 'view'],
            ['name' => 'facturation.manage', 'module' => 'facturation',    'action' => 'manage'],
            // Rapports
            ['name' => 'rapports.view',      'module' => 'rapports',       'action' => 'view'],
            // Administration
            ['name' => 'admin.users',        'module' => 'administration', 'action' => 'manage'],
            ['name' => 'admin.roles',        'module' => 'administration', 'action' => 'manage'],
            ['name' => 'admin.settings',     'module' => 'administration', 'action' => 'manage'],
        ];

        foreach ($permissionsData as $p) {
            Permission::firstOrCreate(['name' => $p['name']], $p);
        }

        $allPermIds      = Permission::pluck('id');
        $nonAdminPermIds = Permission::where('module', '!=', 'administration')->pluck('id');

        // ── 2. Rôles & permissions ─────────────────────────────────────────

        /** @var Role $admin */
        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrateur', 'description' => 'Accès complet à toutes les fonctionnalités.']
        );
        $admin->permissions()->sync($allPermIds);

        /** @var Role $avocat */
        $avocat = Role::firstOrCreate(
            ['name' => 'avocat'],
            ['display_name' => 'Avocat', 'description' => 'Accès complet aux modules métier, sans administration.']
        );
        $avocat->permissions()->sync($nonAdminPermIds);

        /** @var Role $secretaire */
        $secretaire = Role::firstOrCreate(
            ['name' => 'secretaire'],
            ['display_name' => 'Secrétaire', 'description' => 'Saisie et consultation limitées.']
        );
        $secretaire->permissions()->sync(
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

        /** @var Role $stagiaire */
        $stagiaire = Role::firstOrCreate(
            ['name' => 'stagiaire'],
            ['display_name' => 'Stagiaire', 'description' => 'Consultation en lecture seule.']
        );
        $stagiaire->permissions()->sync(
            Permission::whereIn('name', [
                'affaires.view',
                'clients.view',
                'calendrier.view',
                'documents.view',
            ])->pluck('id')
        );

        // ── 3. Utilisateurs ───────────────────────────────────────────────

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@avocatiquewebx.ma'],
            ['name' => 'Admin', 'password' => Hash::make('admin123'), 'status' => 'active']
        );
        $adminUser->roles()->syncWithoutDetaching([$admin->id]);

        $avocatUser = User::firstOrCreate(
            ['email' => 'avocat@avocatiquewebx.ma'],
            ['name' => 'Me. Karim', 'password' => Hash::make('avocat123'), 'status' => 'active']
        );
        $avocatUser->roles()->syncWithoutDetaching([$avocat->id]);

        $saraUser = User::firstOrCreate(
            ['email' => 'sara@avocatiquewebx.ma'],
            ['name' => 'Sara B.', 'password' => Hash::make('sara1234'), 'status' => 'active']
        );
        $saraUser->roles()->syncWithoutDetaching([$secretaire->id]);

        $youssefUser = User::firstOrCreate(
            ['email' => 'youssef@avocatiquewebx.ma'],
            ['name' => 'Youssef M.', 'password' => Hash::make('youssef1234'), 'status' => 'inactive']
        );
        $youssefUser->roles()->syncWithoutDetaching([$stagiaire->id]);

        // ── 4. Menu (éléments par défaut) ─────────────────────────────────
        //
        // On crée d'abord les parents, puis les enfants.
        // Chaque élément est associé aux rôles qui peuvent le voir.

        $allRoles    = [$admin->id, $avocat->id, $secretaire->id, $stagiaire->id];
        $seniorRoles = [$admin->id, $avocat->id];
        $adminOnly   = [$admin->id];

        $parents = [
            'dashboard' => $this->menuItem([
                'label_fr'   => 'Tableau de bord',
                'label_ar'   => 'لوحة التحكم',
                'label_en'   => 'Dashboard',
                'icon'       => 'LayoutDashboard',
                'route'      => '/dashboard',
                'ordre'      => 0,
                'module'     => 'general',
            ], $allRoles),

            'affaires' => $this->menuItem([
                'label_fr'   => 'Affaires',
                'label_ar'   => 'القضايا',
                'label_en'   => 'Cases',
                'icon'       => 'Gavel',
                'route'      => '/affaires',
                'ordre'      => 1,
                'module'     => 'affaires',
            ], $allRoles),

            'clients' => $this->menuItem([
                'label_fr'   => 'Clients',
                'label_ar'   => 'العملاء',
                'label_en'   => 'Clients',
                'icon'       => 'Users',
                'route'      => '/clients',
                'ordre'      => 2,
                'module'     => 'clients',
            ], $allRoles),

            'calendrier' => $this->menuItem([
                'label_fr'   => 'Calendrier',
                'label_ar'   => 'التقويم',
                'label_en'   => 'Calendar',
                'icon'       => 'CalendarDays',
                'route'      => '/calendrier',
                'ordre'      => 3,
                'module'     => 'general',
            ], $allRoles),

            'documents' => $this->menuItem([
                'label_fr'   => 'Documents',
                'label_ar'   => 'الوثائق',
                'label_en'   => 'Documents',
                'icon'       => 'FileText',
                'route'      => '/documents',
                'ordre'      => 4,
                'module'     => 'general',
            ], $allRoles),

            'facturation' => $this->menuItem([
                'label_fr'   => 'Facturation',
                'label_ar'   => 'الفوترة',
                'label_en'   => 'Billing',
                'icon'       => 'Receipt',
                'route'      => '/facturation',
                'ordre'      => 5,
                'module'     => 'facturation',
            ], $seniorRoles),

            'rapports' => $this->menuItem([
                'label_fr'   => 'Rapports',
                'label_ar'   => 'التقارير',
                'label_en'   => 'Reports',
                'icon'       => 'BarChart3',
                'route'      => '/rapports',
                'ordre'      => 6,
                'module'     => 'general',
            ], $seniorRoles),

            'administration' => $this->menuItem([
                'label_fr'   => 'Administration',
                'label_ar'   => 'الإدارة',
                'label_en'   => 'Administration',
                'icon'       => 'Settings',
                'route'      => null,
                'ordre'      => 7,
                'module'     => 'administration',
            ], $adminOnly),
        ];

        // Enfants de "Administration"
        $adminParentId = $parents['administration']->id;

        $this->menuItem([
            'parent_id'  => $adminParentId,
            'label_fr'   => 'Utilisateurs',
            'label_ar'   => 'المستخدمون',
            'label_en'   => 'Users',
            'icon'       => 'Users',
            'route'      => '/admin/users',
            'ordre'      => 0,
            'module'     => 'administration',
        ], $adminOnly);

        $this->menuItem([
            'parent_id'  => $adminParentId,
            'label_fr'   => 'Rôles & Permissions',
            'label_ar'   => 'الأدوار والصلاحيات',
            'label_en'   => 'Roles & Permissions',
            'icon'       => 'Shield',
            'route'      => '/admin/roles',
            'ordre'      => 1,
            'module'     => 'administration',
        ], $adminOnly);

        $this->menuItem([
            'parent_id'  => $adminParentId,
            'label_fr'   => 'Gestion du menu',
            'label_ar'   => 'إدارة القائمة',
            'label_en'   => 'Menu Management',
            'icon'       => 'MenuIcon',
            'route'      => '/admin/menu',
            'ordre'      => 2,
            'module'     => 'administration',
        ], $adminOnly);
    }

    /**
     * Crée (ou retrouve) un MenuItem et synchronise ses rôles.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int>            $roleIds
     */
    private function menuItem(array $data, array $roleIds): MenuItem
    {
        $data = array_merge([
            'parent_id'  => null,
            'is_visible' => true,
        ], $data);

        $item = MenuItem::firstOrCreate(
            [
                'label_fr'  => $data['label_fr'],
                'parent_id' => $data['parent_id'],
            ],
            $data
        );

        $item->roles()->sync($roleIds);

        return $item;
    }
}
