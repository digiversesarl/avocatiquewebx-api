<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class CabinetConfig extends Model
{
    use Auditable;
    protected $table = 'cabinet_configs';

    protected $fillable = [
        'firm_name_fr',
        'firm_name_ar',
        'firm_name_en',
        'firm_address_fr',
        'firm_address_ar',
        'firm_address_en',
        'city_fr',
        'city_ar',
        'city_en',
        'firm_email',
        'firm_phone',
        'firm_fax',
        'firm_site_web',
        'firm_code',
        'firm_barreau',
        'firm_sms_number',
        'firm_patente',
        'firm_ice',
        'firm_cnss',
        'firm_banque',
        'firm_compte_bancaire',
        'firm_agence',
        'firm_logo_url',
        'firm_header_url',
        'firm_footer_url',
        'firm_signature_url',
        'color_theme',
    ];

    /**
     * Obtenir ou créer la configuration du cabinet (singleton)
     */
    public static function getConfig(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'firm_name_fr' => 'Cabinet Maître Exemple',
                'firm_name_ar' => 'مكتب الأستاذ مثال',
                'color_theme' => 'green',
            ]
        );
    }
}
