<?php

namespace App\Services;

use Illuminate\Support\Collection;

class PaysExportService extends BasePdfExportService
{
    /**
     * Générer un PDF pour l'export de pays
     */
    public function generatePdf(Collection $pays, string $language = 'fr', string $title = 'Pays', string $filename = 'pays.pdf'): string
    {
        $html = $this->buildPaysHtml($pays, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    /**
     * Construire le HTML pour le PDF des pays
     */
    private function buildPaysHtml(Collection $pays, string $language, string $title): string
    {
        // Sélectionner la clé de label en fonction de la langue
        if ($language === 'ar') {
            $labelKey = 'label_ar';
        } elseif ($language === 'en') {
            $labelKey = 'label_en';
        } else {
            $labelKey = 'label_fr';
        }

        // Définir les en-têtes de colonne
        $headerLabels = [
            ['fr' => 'Pays',         'en' => 'Country', 'ar' => 'الدولة',      'width' => ''],   // auto
            ['fr' => 'Code',         'en' => 'Code',    'ar' => 'الكود',       'width' => '8%'],  // court
            ['fr' => 'Ordre',        'en' => 'Order',   'ar' => 'الترتيب',     'width' => '8%'],  // court
            ['fr' => 'Couleur fond', 'en' => '...',     'ar' => 'اللون الحلقي','width' => ''],   // auto
            ['fr' => 'Couleur texte','en' => '...',     'ar' => 'لون النص',    'width' => ''],   // auto
            ['fr' => 'Défaut',       'en' => '...',     'ar' => 'افتراضي',     'width' => '8%'],  // court
            ['fr' => 'Actif',        'en' => '...',     'ar' => 'نشط',         'width' => '8%'],  // court
        ];

        $headers = $this->getTableHeaders($language, $headerLabels);
        $rows = $this->buildPaysRows($pays, $language, $labelKey);

        return $this->buildPdfHtml($title, $language, $headers, $rows);
    }

    /**
     * Construire les lignes du tableau des pays
     */
    private function buildPaysRows(Collection $pays, string $language, string $labelKey): string
    {
        $rows = '';
        
        foreach ($pays as $country) {
            $isActive = $country->is_active !== false ? '✓' : '';
            $isDefault = $country->is_default ? '✓' : '';
            $dirStyle = $language === 'ar' ? 'direction: rtl;' : '';
            
            // Créer les boîtes colorées pour bg_color et text_color
            $bgColor = $country->bg_color ?? '#ffffff';
            $textColor = $country->text_color ?? '#73879c';
            // Utiliser une cellule HTML simple pour mPDF
            $bgColorBox = '<span style="background-color: ' . $bgColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($bgColor);
            $textColorBox = '<span style="background-color: ' . $textColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($textColor);
            
            $rows .= '<tr>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $country->id . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; ' . $dirStyle . '">' . htmlspecialchars($country->$labelKey) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($country->code ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($country->classement ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $bgColorBox . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $textColorBox . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $isDefault . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $isActive . '</td>
                </tr>';
        }
        
        return $rows;
    }
}
