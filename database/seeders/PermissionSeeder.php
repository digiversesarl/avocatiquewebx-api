<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionsData = [
            ['name' => 'affaires.view', 'module' => 'affaires', 'action' => 'view'],
            ['name' => 'affaires.create', 'module' => 'affaires', 'action' => 'create'],
            ['name' => 'affaires.edit', 'module' => 'affaires', 'action' => 'edit'],
            ['name' => 'affaires.delete', 'module' => 'affaires', 'action' => 'delete'],
            ['name' => 'clients.view', 'module' => 'clients', 'action' => 'view'],
            ['name' => 'clients.create', 'module' => 'clients', 'action' => 'create'],
            ['name' => 'clients.edit', 'module' => 'clients', 'action' => 'edit'],
            ['name' => 'clients.delete', 'module' => 'clients', 'action' => 'delete'],
            ['name' => 'calendrier.view', 'module' => 'calendrier', 'action' => 'view'],
            ['name' => 'calendrier.manage', 'module' => 'calendrier', 'action' => 'manage'],
            ['name' => 'documents.view', 'module' => 'documents', 'action' => 'view'],
            ['name' => 'documents.upload', 'module' => 'documents', 'action' => 'upload'],
            ['name' => 'documents.delete', 'module' => 'documents', 'action' => 'delete'],
            ['name' => 'facturation.view', 'module' => 'facturation', 'action' => 'view'],
            ['name' => 'facturation.manage', 'module' => 'facturation', 'action' => 'manage'],
            ['name' => 'rapports.view', 'module' => 'rapports', 'action' => 'view'],
            ['name' => 'users.view', 'module' => 'users', 'action' => 'view'],
            ['name' => 'users.create', 'module' => 'users', 'action' => 'create'],
            ['name' => 'users.edit', 'module' => 'users', 'action' => 'edit'],
            ['name' => 'users.delete', 'module' => 'users', 'action' => 'delete'],
            ['name' => 'menu.view', 'module' => 'menu', 'action' => 'view'],
            ['name' => 'menu.create', 'module' => 'menu', 'action' => 'create'],
            ['name' => 'menu.edit', 'module' => 'menu', 'action' => 'edit'],
            ['name' => 'menu.delete', 'module' => 'menu', 'action' => 'delete'],
            ['name' => 'admin.users', 'module' => 'administration', 'action' => 'manage'],
            ['name' => 'admin.roles', 'module' => 'administration', 'action' => 'manage'],
            ['name' => 'admin.settings', 'module' => 'administration', 'action' => 'manage'],
            ['name' => 'admin.translations', 'module' => 'administration', 'action' => 'manage'],
            ['name' => 'admin.cabinet', 'module' => 'administration', 'action' => 'manage'],
            ['name' => 'admin.audit', 'module' => 'administration', 'action' => 'manage'],
        ];

        foreach ($permissionsData as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}