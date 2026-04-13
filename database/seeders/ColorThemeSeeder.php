<?php

namespace Database\Seeders;

use App\Models\ColorTheme;
use Illuminate\Database\Seeder;

class ColorThemeSeeder extends Seeder
{
    public function run(): void
    {
        $themes = [
            ['slug' => 'violet', 'label' => 'Violet moderne',       'color1' => '#6D28D9', 'color2' => '#A855F7', 'color3' => '#F472B6', 'is_default' => true, 'classement' => 1],
            ['slug' => 'blue',   'label' => 'Bleu professionnel',    'color1' => '#2563EB', 'color2' => '#60A5FA', 'color3' => '#22C55E', 'is_default' => true, 'classement' => 2],
            ['slug' => 'indigo', 'label' => 'Indigo futuriste',      'color1' => '#6366F1', 'color2' => '#818CF8', 'color3' => '#06B6D4', 'is_default' => true, 'classement' => 3],
            ['slug' => 'green',  'label' => 'Vert juridique',        'color1' => '#065F46', 'color2' => '#10B981', 'color3' => '#F59E0B', 'is_default' => true, 'classement' => 4],
            ['slug' => 'navy',   'label' => 'Navy & Gold',           'color1' => '#0F172A', 'color2' => '#1E293B', 'color3' => '#FBBF24', 'is_default' => true, 'classement' => 5],
        ];

        foreach ($themes as $theme) {
            ColorTheme::updateOrCreate(
                ['slug' => $theme['slug']],
                $theme
            );
        }
    }
}
