<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class FonctionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('fonctions')->insert([
            [
                'code' => 'AVT_SR',
                'label_fr' => 'Avocat Titulaire Seniors',
                'label_ar' => 'محامي رسمي أول',
                'label_en' => 'Senior Titular Lawyer',
                'classement' => 1,
                'is_active' => true,
                'bg_color' => '#ffffff',
                'text_color' => '#73879c',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'AVT',
                'label_fr' => 'Avocat Titulaire',
                'label_ar' => 'محامي رسمي',
                'label_en' => 'Titular Lawyer',
                'classement' => 2,
                'is_active' => true,
                'bg_color' => '#ffffff',
                'text_color' => '#73879c',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'AVC',
                'label_fr' => 'Avocat Collaborateur',
                'label_ar' => 'محامي متعاون',
                'label_en' => 'Associate Lawyer',
                'classement' => 3,
                'is_active' => true,
                'bg_color' => '#ffffff',
                'text_color' => '#73879c',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'SEC',
                'label_fr' => 'Secrétaire',
                'label_ar' => 'سكرتير',
                'label_en' => 'Secretary',
                'classement' => 4,
                'is_active' => true,
                'bg_color' => '#ffffff',
                'text_color' => '#73879c',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'STG',
                'label_fr' => 'Stagiaire',
                'label_ar' => 'متدرب',
                'label_en' => 'Intern',
                'classement' => 5,
                'is_active' => true,
                'bg_color' => '#ffffff',
                'text_color' => '#73879c',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'AGT',
                'label_fr' => 'Agent',
                'label_ar' => 'عون',
                'label_en' => 'Agent',
                'classement' => 6,
                'is_active' => true,
                'bg_color' => '#ffffff',
                'text_color' => '#73879c',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}