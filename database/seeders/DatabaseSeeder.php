<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            FonctionSeeder::class,
            DepartementSeeder::class,
            GradeSeeder::class,
            GroupeSeeder::class,
            UserSeeder::class,
            MenuSeeder::class,
            PaysSeeder::class,
            VilleSeeder::class,
            TranslationSeeder::class,
        ]);
    }
}
