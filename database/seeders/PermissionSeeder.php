<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionsData = [
            // Affaires
            ['name' => 'affaires.view', 'module' => 'affaires', 'action' => 'view'],
            ['name' => 'affaires.create', 'module' => 'affaires', 'action' => 'create'],
            ['name' => 'affaires.edit', 'module' => 'affaires', 'action' => 'edit'],
            ['name' => 'affaires.delete', 'module' => 'affaires', 'action' => 'delete'],
            ['name' => 'affaires.export', 'module' => 'affaires', 'action' => 'export'],

            // Clients
            ['name' => 'clients.view', 'module' => 'clients', 'action' => 'view'],
            ['name' => 'clients.create', 'module' => 'clients', 'action' => 'create'],
            ['name' => 'clients.edit', 'module' => 'clients', 'action' => 'edit'],
            ['name' => 'clients.delete', 'module' => 'clients', 'action' => 'delete'],
            ['name' => 'clients.export', 'module' => 'clients', 'action' => 'export'],

            // Calendrier
            ['name' => 'calendrier.view', 'module' => 'calendrier', 'action' => 'view'],
            ['name' => 'calendrier.manage', 'module' => 'calendrier', 'action' => 'manage'],

            // Documents
            ['name' => 'documents.view', 'module' => 'documents', 'action' => 'view'],
            ['name' => 'documents.upload', 'module' => 'documents', 'action' => 'upload'],
            ['name' => 'documents.delete', 'module' => 'documents', 'action' => 'delete'],

            // Facturation
            ['name' => 'facturation.view', 'module' => 'facturation', 'action' => 'view'],
            ['name' => 'facturation.manage', 'module' => 'facturation', 'action' => 'manage'],

            // Rapports
            ['name' => 'rapports.view', 'module' => 'rapports', 'action' => 'view'],

            // Administration
            ['name' => 'admin.users', 'module' => 'admin', 'action' => 'manage'],
            ['name' => 'admin.roles', 'module' => 'admin', 'action' => 'manage'],
            ['name' => 'admin.settings', 'module' => 'admin', 'action' => 'manage'],
            ['name' => 'admin.cabinet', 'module' => 'admin', 'action' => 'manage'],

            // Users
            ['name' => 'users.view', 'module' => 'users', 'action' => 'view'],
            ['name' => 'users.create', 'module' => 'users', 'action' => 'create'],
            ['name' => 'users.edit', 'module' => 'users', 'action' => 'edit'],
            ['name' => 'users.delete', 'module' => 'users', 'action' => 'delete'],
            ['name' => 'users.export', 'module' => 'users', 'action' => 'export'],

            // Translations
            ['name' => 'translations.view', 'module' => 'translations', 'action' => 'view'],
            ['name' => 'translations.create', 'module' => 'translations', 'action' => 'create'],
            ['name' => 'translations.edit', 'module' => 'translations', 'action' => 'edit'],
            ['name' => 'translations.delete', 'module' => 'translations', 'action' => 'delete'],

            // Menu
            ['name' => 'menu.view', 'module' => 'menu', 'action' => 'view'],
            ['name' => 'menu.create', 'module' => 'menu', 'action' => 'create'],
            ['name' => 'menu.edit', 'module' => 'menu', 'action' => 'edit'],
            ['name' => 'menu.delete', 'module' => 'menu', 'action' => 'delete'],

            // Référentiels - Fonctions
            ['name' => 'ref.fonctions.view', 'module' => 'ref_fonctions', 'action' => 'view'],
            ['name' => 'ref.fonctions.create', 'module' => 'ref_fonctions', 'action' => 'create'],
            ['name' => 'ref.fonctions.edit', 'module' => 'ref_fonctions', 'action' => 'edit'],
            ['name' => 'ref.fonctions.delete', 'module' => 'ref_fonctions', 'action' => 'delete'],
            ['name' => 'ref.fonctions.export', 'module' => 'ref_fonctions', 'action' => 'export'],

            // Référentiels - Grades
            ['name' => 'ref.grades.view', 'module' => 'ref_grades', 'action' => 'view'],
            ['name' => 'ref.grades.create', 'module' => 'ref_grades', 'action' => 'create'],
            ['name' => 'ref.grades.edit', 'module' => 'ref_grades', 'action' => 'edit'],
            ['name' => 'ref.grades.delete', 'module' => 'ref_grades', 'action' => 'delete'],
            ['name' => 'ref.grades.export', 'module' => 'ref_grades', 'action' => 'export'],

            // Référentiels - Départements
            ['name' => 'ref.departements.view', 'module' => 'ref_departements', 'action' => 'view'],
            ['name' => 'ref.departements.create', 'module' => 'ref_departements', 'action' => 'create'],
            ['name' => 'ref.departements.edit', 'module' => 'ref_departements', 'action' => 'edit'],
            ['name' => 'ref.departements.delete', 'module' => 'ref_departements', 'action' => 'delete'],
            ['name' => 'ref.departements.export', 'module' => 'ref_departements', 'action' => 'export'],

            // Référentiels - Groupes
            ['name' => 'ref.groupes.view', 'module' => 'ref_groupes', 'action' => 'view'],
            ['name' => 'ref.groupes.create', 'module' => 'ref_groupes', 'action' => 'create'],
            ['name' => 'ref.groupes.edit', 'module' => 'ref_groupes', 'action' => 'edit'],
            ['name' => 'ref.groupes.delete', 'module' => 'ref_groupes', 'action' => 'delete'],
            ['name' => 'ref.groupes.export', 'module' => 'ref_groupes', 'action' => 'export'],

            // Référentiels - Pays
            ['name' => 'ref.pays.view', 'module' => 'ref_pays', 'action' => 'view'],
            ['name' => 'ref.pays.create', 'module' => 'ref_pays', 'action' => 'create'],
            ['name' => 'ref.pays.edit', 'module' => 'ref_pays', 'action' => 'edit'],
            ['name' => 'ref.pays.delete', 'module' => 'ref_pays', 'action' => 'delete'],
            ['name' => 'ref.pays.export', 'module' => 'ref_pays', 'action' => 'export'],

            // Référentiels - Villes
            ['name' => 'ref.villes.view', 'module' => 'ref_villes', 'action' => 'view'],
            ['name' => 'ref.villes.create', 'module' => 'ref_villes', 'action' => 'create'],
            ['name' => 'ref.villes.edit', 'module' => 'ref_villes', 'action' => 'edit'],
            ['name' => 'ref.villes.delete', 'module' => 'ref_villes', 'action' => 'delete'],
            ['name' => 'ref.villes.export', 'module' => 'ref_villes', 'action' => 'export'],
        ];

        foreach ($permissionsData as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}