<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('groupes')->insert([
            [
                'code' => 'HUISS',
                'label_fr' => 'Huissiers',
                'label_ar' => 'المفوضون القضائيون',
                'label_en' => 'Bailiffs',
                'classement' => 1,
                'is_active' => true,
                'bg_color' => '#efebe9',
                'text_color' => '#4e342e',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ASSD',
                'label_fr' => 'Agents Diligences',
                'label_ar' => 'أعوان التنفيذ',
                'label_en' => 'Diligence Agents',
                'classement' => 2,
                'is_active' => true,
                'bg_color' => '#e8f5e9',
                'text_color' => '#1b5e20',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'AVDA',
                'label_fr' => 'Avocats Audiences',
                'label_ar' => 'محامو الجلسات',
                'label_en' => 'Hearing Lawyers',
                'classement' => 3,
                'is_active' => true,
                'bg_color' => '#e3f2fd',
                'text_color' => '#0d47a1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}