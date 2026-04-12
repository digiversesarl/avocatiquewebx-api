<?php

// config/pdf.php

return [

    /*
    |--------------------------------------------------------------------------
    | Filigrane PDF
    |--------------------------------------------------------------------------
    | Laisser watermark_image à null pour désactiver globalement.
    */

    'watermark_image' => public_path('images/filigrane.png'),
    'watermark_alpha' => 0.2,   // 0.0 = opaque, 1.0 = invisible
    'watermark_size'  => 'D',   // 'D' = taille défaut mPDF, 'P' = pleine page, ou [w, h] en mm
    'watermark_pos'   => [5, 5], // [x, y] en mm depuis le coin haut-gauche

];