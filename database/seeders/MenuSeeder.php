<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
		
		
		$roleSuperAdmin = Role::where('name', 'superadmin')->firstOrFail();
		$roleAdmin      = Role::where('name', 'admin')->firstOrFail();
		$roleAvocat     = Role::where('name', 'avocat')->firstOrFail();
		$roleSecretaire = Role::where('name', 'secretaire')->firstOrFail();
		$roleStagiaire  = Role::where('name', 'stagiaire')->firstOrFail();
		$roleAgent      = Role::where('name', 'agent')->firstOrFail();

		
        $allRoles    = [$roleAdmin->id, $roleAvocat->id, $roleSecretaire->id, $roleStagiaire->id, $roleAgent->id];
        $seniorRoles = [$roleAdmin->id, $roleAvocat->id];
        $adminOnly   = [$roleAdmin->id];

        $parents = [
            'dashboard' => $this->menuItem([
                'label_fr' => 'Tableau de bord',
                'label_ar' => 'لوحة التحكم',
                'label_en' => 'Dashboard',
                'icon'     => 'LayoutDashboard',
                'route'    => '/dashboard',
                'ordre'    => 0,
                'module'   => 'general',
            ], $allRoles),

            'affaires' => $this->menuItem([
                'label_fr' => 'Affaires',
                'label_ar' => 'القضايا',
                'label_en' => 'Cases',
                'icon'     => 'Gavel',
                'route'    => '/affaires',
                'ordre'    => 1,
                'module'   => 'affaires',
            ], $allRoles),

            'clients' => $this->menuItem([
                'label_fr' => 'Clients',
                'label_ar' => 'العملاء',
                'label_en' => 'Clients',
                'icon'     => 'Users',
                'route'    => '/clients',
                'ordre'    => 2,
                'module'   => 'clients',
            ], $allRoles),

            'calendrier' => $this->menuItem([
                'label_fr' => 'Calendrier',
                'label_ar' => 'التقويم',
                'label_en' => 'Calendar',
                'icon'     => 'CalendarDays',
                'route'    => '/calendrier',
                'ordre'    => 3,
                'module'   => 'general',
            ], $allRoles),

            'documents' => $this->menuItem([
                'label_fr' => 'Documents',
                'label_ar' => 'الوثائق',
                'label_en' => 'Documents',
                'icon'     => 'FileText',
                'route'    => '/documents',
                'ordre'    => 4,
                'module'   => 'general',
            ], $allRoles),

            'facturation' => $this->menuItem([
                'label_fr' => 'Facturation',
                'label_ar' => 'الفوترة',
                'label_en' => 'Billing',
                'icon'     => 'Receipt',
                'route'    => '/facturation',
                'ordre'    => 5,
                'module'   => 'facturation',
            ], $seniorRoles),

            'rapports' => $this->menuItem([
                'label_fr' => 'Rapports',
                'label_ar' => 'التقارير',
                'label_en' => 'Reports',
                'icon'     => 'BarChart3',
                'route'    => '/rapports',
                'ordre'    => 6,
                'module'   => 'general',
            ], $seniorRoles),

            'referentiel' => $this->menuItem([
                'label_fr' => 'Référentiel',
                'label_ar' => 'المرجعيات',
                'label_en' => 'Reference Data',
                'icon'     => 'BookOpen',
                'route'    => null,
                'ordre'    => 8,
                'module'   => 'referentiel',
            ], $seniorRoles),

            'administration' => $this->menuItem([
                'label_fr' => 'Administration',
                'label_ar' => 'الإدارة',
                'label_en' => 'Administration',
                'icon'     => 'Settings',
                'route'    => null,
                'ordre'    => 9,
                'module'   => 'administration',
            ], $adminOnly),
        ];

        // ── Enfants de "Référentiel" ───────────────────────────────────────
        $referentielParentId = $parents['referentiel']->id;

        $this->menuItem([
            'parent_id' => $referentielParentId,
            'label_fr'  => 'Fonctions',
            'label_ar'  => 'الوظائف',
            'label_en'  => 'Functions',
            'icon'      => 'ClipboardList',
            'route'     => '/referentiel/fonctions',
            'ordre'     => 0,
            'module'    => 'referentiel',
        ], $seniorRoles);

        $this->menuItem([
            'parent_id' => $referentielParentId,
            'label_fr'  => 'Grades Avocat',
            'label_ar'  => 'درجات المحامي',
            'label_en'  => 'Lawyer Grades',
            'icon'      => 'Award',
            'route'     => '/referentiel/grades',
            'ordre'     => 1,
            'module'    => 'referentiel',
        ], $seniorRoles);

        $this->menuItem([
            'parent_id' => $referentielParentId,
            'label_fr'  => 'Départements',
            'label_ar'  => 'الأقسام',
            'label_en'  => 'Departments',
            'icon'      => 'Building2',
            'route'     => '/referentiel/departements',
            'ordre'     => 2,
            'module'    => 'referentiel',
        ], $seniorRoles);

        $this->menuItem([
            'parent_id' => $referentielParentId,
            'label_fr'  => 'Groupes',
            'label_ar'  => 'المجموعات',
            'label_en'  => 'Groups',
            'icon'      => 'UsersRound',
            'route'     => '/referentiel/groupes',
            'ordre'     => 3,
            'module'    => 'referentiel',
        ], $seniorRoles);

        $this->menuItem([
            'parent_id' => $referentielParentId,
            'label_fr'  => 'Villes',
            'label_ar'  => 'المدن',
            'label_en'  => 'Cities',
            'icon'      => 'MapPin',
            'route'     => '/referentiel/villes',
            'ordre'     => 4,
            'module'    => 'referentiel',
        ], $seniorRoles);

        $this->menuItem([
            'parent_id' => $referentielParentId,
            'label_fr'  => 'Pays',
            'label_ar'  => 'الدول',
            'label_en'  => 'Countries',
            'icon'      => 'Globe',
            'route'     => '/referentiel/pays',
            'ordre'     => 5,
            'module'    => 'referentiel',
        ], $seniorRoles);

        // ── Enfants de "Administration" ────────────────────────────────────
        $adminParentId = $parents['administration']->id;

        $this->menuItem([
            'parent_id' => $adminParentId,
            'label_fr'  => 'Utilisateurs',
            'label_ar'  => 'المستخدمون',
            'label_en'  => 'Users',
            'icon'      => 'Users',
            'route'     => '/admin/users',
            'ordre'     => 0,
            'module'    => 'administration',
        ], $adminOnly);

        $this->menuItem([
            'parent_id' => $adminParentId,
            'label_fr'  => 'Rôles & Permissions',
            'label_ar'  => 'الأدوار والصلاحيات',
            'label_en'  => 'Roles & Permissions',
            'icon'      => 'Shield',
            'route'     => '/admin/roles',
            'ordre'     => 1,
            'module'    => 'administration',
        ], $adminOnly);

        $this->menuItem([
            'parent_id' => $adminParentId,
            'label_fr'  => 'Gestion du menu',
            'label_ar'  => 'إدارة القائمة',
            'label_en'  => 'Menu Management',
            'icon'      => 'MenuIcon',
            'route'     => '/admin/menu',
            'ordre'     => 2,
            'module'    => 'administration',
        ], $adminOnly);

        $this->menuItem([
            'parent_id' => $adminParentId,
            'label_fr'  => 'Traductions',
            'label_ar'  => 'الترجمات',
            'label_en'  => 'Translations',
            'icon'      => 'Languages',
            'route'     => '/admin/translations',
            'ordre'     => 3,
            'module'    => 'administration',
        ], $adminOnly);

        $this->menuItem([
            'parent_id' => $adminParentId,
            'label_fr'  => 'Config. Cabinet',
            'label_ar'  => 'إعدادات المكتب',
            'label_en'  => 'Firm Config',
            'icon'      => 'Building2',
            'route'     => '/admin/cabinet',
            'ordre'     => 4,
            'module'    => 'administration',
        ], $adminOnly);

        $this->menuItem([
            'parent_id' => $adminParentId,
            'label_fr'  => "Journal d'audit",
            'label_ar'  => 'سجل التدقيق',
            'label_en'  => 'Audit Log',
            'icon'      => 'ShieldAlert',
            'route'     => '/admin/audit',
            'ordre'     => 5,
            'module'    => 'administration',
        ], $adminOnly);
    }

    private function menuItem(array $data, array $roleIds): MenuItem
    {
        $data = array_merge([
            'parent_id' => null,
            'is_visible' => true,
        ], $data);

        $item = MenuItem::firstOrCreate([
            'label_fr' => $data['label_fr'],
            'parent_id' => $data['parent_id'],
        ], $data);

        $item->roles()->sync($roleIds);
        return $item;
    }
}
