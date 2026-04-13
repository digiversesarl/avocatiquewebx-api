<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColorTheme extends Model
{
    protected $table = 'color_themes';

    protected $fillable = [
        'slug',
        'label',
        'color1',
        'color2',
        'color3',
        'is_default',
        'classement',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'classement' => 'integer',
    ];
}
