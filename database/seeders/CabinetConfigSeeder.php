<?php

namespace Database\Seeders;

use App\Models\CabinetConfig;
use Illuminate\Database\Seeder;

class CabinetConfigSeeder extends Seeder
{
    public function run(): void
    {
        CabinetConfig::updateOrCreate(
            ['id' => 1],
            [
                'firm_name_fr' => 'Cabinet Maître Exemple',
                'firm_name_ar' => 'مكتب الأستاذ مثال',
                'firm_name_en' => 'Example Law Firm',
                'firm_address_fr' => '123 Avenue Mohammed V, Étage 3, Bureau 12',
                'firm_address_ar' => 'شارع محمد الخامس 123، الطابق 3، مكتب 12',
                'firm_address_en' => '123 Mohammed V Avenue, Floor 3, Office 12',
                'city_fr' => 'Casablanca',
                'city_ar' => 'الدار البيضاء',
                'city_en' => 'Casablanca',
                'firm_email' => 'contact@cabinet-exemple.ma',
                'firm_phone' => '+212 522 123 456',
                'firm_fax' => '+212 522 123 457',
                'firm_site_web' => 'www.cabinet-exemple.ma',
                'firm_code' => 'CAB-001',
                'firm_barreau' => 'Barreau de Casablanca',
                'firm_sms_number' => '+212 600 123 456',
                'firm_patente' => '12345678',
                'firm_ice' => '001234567000012',
                'firm_cnss' => '9876543',
                'firm_banque' => 'Attijariwafa Bank',
                'firm_compte_bancaire' => '007 810 0001234567890123 45',
                'firm_agence' => 'Agence Maarif',
                'color_theme' => 'green',
            ]
        );
    }
}
