<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departements = [
            [
                'code' => 'CAB',
                'label_fr' => 'Cabinet',
                'label_ar' => 'المكتب',
                'label_en' => 'Office',
                'abbreviation' => 'CAB',
                'classement' => 1,
                'is_active' => true,
                'is_default' => false,
                'bg_color' => '#e8eaf6',
                'text_color' => '#283593',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ASS',
                'label_fr' => 'Dép-Assurances',
                'label_ar' => 'قسم التأمينات',
                'label_en' => 'Insurance Dept.',
                'abbreviation' => 'ASS',
                'classement' => 2,
                'is_active' => true,
                'is_default' => false,
                'bg_color' => '#fce4ec',
                'text_color' => '#c62828',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'INF',
                'label_fr' => 'Département-Informatique',
                'label_ar' => 'قسم المعلوميات',
                'label_en' => 'IT Department',
                'abbreviation' => 'INF',
                'classement' => 3,
                'is_active' => true,
                'is_default' => false,
                'bg_color' => '#e0f7fa',
                'text_color' => '#00695c',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'RD',
                'label_fr' => 'Département-Risques Divers',
                'label_ar' => 'قسم المخاطر المتنوعة',
                'label_en' => 'Miscellaneous Risks Dept.',
                'abbreviation' => 'RD',
                'classement' => 4,
                'is_active' => true,
                'is_default' => false,
                'bg_color' => '#f3e5f5',
                'text_color' => '#6a1b9a',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'AUTO',
                'label_fr' => 'Département-Auto',
                'label_ar' => 'قسم السيارات',
                'label_en' => 'Auto Department',
                'abbreviation' => 'AUTO',
                'classement' => 5,
                'is_active' => true,
                'is_default' => false,
                'bg_color' => '#fff8e1',
                'text_color' => '#f57f17',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('departements')->insert($departements);
    }
}