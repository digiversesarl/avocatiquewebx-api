<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DepartementExportService extends BasePdfExportService
{
    /**
     * Générer un PDF pour l'export de départements
     */
    public function generatePdf(Collection $departements, string $language = 'fr', string $title = 'Départements', string $filename = 'departements.pdf'): string
    {
        $html = $this->buildDepartementsHtml($departements, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    /**
     * Construire le HTML pour le PDF des départements
     */
    private function buildDepartementsHtml(Collection $departements, string $language, string $title): string
    {
        if ($language === 'ar') {
            $labelKey = 'label_ar';
        } elseif ($language === 'en') {
            $labelKey = 'label_en';
        } else {
            $labelKey = 'label_fr';
        }

        $headerLabels = [
            ['fr' => 'Département',    'en' => 'Department',  'ar' => 'القسم',        'width' => ''],
            ['fr' => 'Abréviation',    'en' => 'Abbr.',       'ar' => 'الاختصار',     'width' => '10%'],
            ['fr' => 'Ordre',          'en' => 'Order',       'ar' => 'الترتيب',      'width' => '8%'],
            ['fr' => 'Couleur fond',   'en' => 'Bg Color',    'ar' => 'اللون الخلفي', 'width' => ''],
            ['fr' => 'Couleur texte',  'en' => 'Text Color',  'ar' => 'لون النص',     'width' => ''],
            ['fr' => 'Défaut',         'en' => 'Default',     'ar' => 'افتراضي',      'width' => '8%'],
            ['fr' => 'Actif',          'en' => 'Active',      'ar' => 'نشط',          'width' => '8%'],
        ];

        $headers = $this->getTableHeaders($language, $headerLabels);
        $rows = $this->buildDepartementsRows($departements, $language, $labelKey);

        return $this->buildPdfHtml($title, $language, $headers, $rows);
    }

    /**
     * Construire les lignes du tableau des départements
     */
    private function buildDepartementsRows(Collection $departements, string $language, string $labelKey): string
    {
        $rows = '';

        foreach ($departements as $dept) {
            $isActive  = $dept->is_active !== false ? '✓' : '';
            $isDefault = $dept->is_default ? '✓' : '';
            $dirStyle  = $language === 'ar' ? 'direction: rtl;' : '';

            $bgColor    = $dept->bg_color ?? '#ffffff';
            $textColor  = $dept->text_color ?? '#73879c';
            $bgColorBox   = '<span style="background-color: ' . $bgColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($bgColor);
            $textColorBox = '<span style="background-color: ' . $textColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($textColor);

            $rows .= '<tr>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $dept->id . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; ' . $dirStyle . '">' . htmlspecialchars($dept->$labelKey ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($dept->abbreviation ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($dept->classement ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $bgColorBox . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $textColorBox . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $isDefault . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $isActive . '</td>
                </tr>';
        }

        return $rows;
    }
}
