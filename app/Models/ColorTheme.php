<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class ColorTheme extends Model
{
    use Auditable;
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
