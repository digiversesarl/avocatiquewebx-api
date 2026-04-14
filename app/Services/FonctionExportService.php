<?php

namespace App\Services;

use Illuminate\Support\Collection;

class FonctionExportService extends BasePdfExportService
{
    /**
     * Générer un PDF pour l'export de fonctions
     */
    public function generatePdf(Collection $fonctions, string $language = 'fr', string $title = 'Fonctions', string $filename = 'fonctions.pdf'): string
    {
        $html = $this->buildFonctionsHtml($fonctions, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    /**
     * Construire le HTML pour le PDF des fonctions
     */
    private function buildFonctionsHtml(Collection $fonctions, string $language, string $title): string
    {
        if ($language === 'ar') {
            $labelKey = 'label_ar';
        } elseif ($language === 'en') {
            $labelKey = 'label_en';
        } else {
            $labelKey = 'label_fr';
        }

        $headerLabels = [
            ['fr' => 'Fonction',       'en' => 'Function',   'ar' => 'الوظيفة',      'width' => ''],
            ['fr' => 'Code',           'en' => 'Code',       'ar' => 'الكود',        'width' => '8%'],
            ['fr' => 'Ordre',          'en' => 'Order',      'ar' => 'الترتيب',      'width' => '8%'],
            ['fr' => 'Couleur fond',   'en' => 'Bg Color',   'ar' => 'اللون الخلفي', 'width' => ''],
            ['fr' => 'Couleur texte',  'en' => 'Text Color', 'ar' => 'لون النص',     'width' => ''],
            ['fr' => 'Défaut',         'en' => 'Default',    'ar' => 'افتراضي',      'width' => '8%'],
            ['fr' => 'Actif',          'en' => 'Active',     'ar' => 'نشط',          'width' => '8%'],
        ];

        $headers = $this->getTableHeaders($language, $headerLabels);
        $rows = $this->buildFonctionsRows($fonctions, $language, $labelKey);

        return $this->buildPdfHtml($title, $language, $headers, $rows);
    }

    /**
     * Construire les lignes du tableau des fonctions
     */
    private function buildFonctionsRows(Collection $fonctions, string $language, string $labelKey): string
    {
        $rows = '';

        foreach ($fonctions as $fonction) {
            $isActive  = $fonction->is_active !== false ? '✓' : '';
            $isDefault = $fonction->is_default ? '✓' : '';
            $dirStyle  = $language === 'ar' ? 'direction: rtl;' : '';

            $bgColor    = $fonction->bg_color ?? '#ffffff';
            $textColor  = $fonction->text_color ?? '#73879c';
            $bgColorBox   = '<span style="background-color: ' . $bgColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($bgColor);
            $textColorBox = '<span style="background-color: ' . $textColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($textColor);

            $rows .= '<tr>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $fonction->id . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; ' . $dirStyle . '">' . htmlspecialchars($fonction->$labelKey ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($fonction->code ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($fonction->classement ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $bgColorBox . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $textColorBox . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $isDefault . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $isActive . '</td>
                </tr>';
        }

        return $rows;
    }
}
