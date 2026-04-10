<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Grade;


class GradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $grades = [
                [
                    'id' => 1,
                    'code' => 'AGC',
                    'label_fr' => "Agréé près la Cour d'Appel",
                    'label_ar' => 'معتمد لدى محكمة الاستئناف',
                    'label_en' => 'Admitted to the Court of Appeal',
                    'classement' => 1,
                    'is_active' => true,
                    'bg_color' => '#e8f5e9',
                    'text_color' => '#2e7d32',
                ],
                [
                    'id' => 2,
                    'code' => 'AGK',
                    'label_fr' => "Agréé près la Cour de Cassation",
                    'label_ar' => 'معتمد لدى محكمة النقض',
                    'label_en' => 'Admitted to the Supreme Court',
                    'classement' => 2,
                    'is_active' => true,
                    'bg_color' => '#e3f2fd',
                    'text_color' => '#1565c0',
                ],
                [
                    'id' => 3,
                    'code' => 'STG',
                    'label_fr' => 'Stagiaire',
                    'label_ar' => 'متدرب',
                    'label_en' => 'Intern',
                    'classement' => 3,
                    'is_active' => true,
                    'bg_color' => '#fff3e0',
                    'text_color' => '#e65100',
                ],
        ];

        foreach ($grades as $grade) {
            Grade::create($grade);
        }
    }
}