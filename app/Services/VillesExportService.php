<?php

namespace App\Services;

use Illuminate\Support\Collection;

class VillesExportService extends BasePdfExportService
{
    /**
     * Générer un PDF pour l'export de villes
     */
    public function generatePdf(Collection $villes, string $language = 'fr', string $title = 'Villes', string $filename = 'villes.pdf', string $userLogin = ''): string
    {
        $html = $this->buildVillesHtml($villes, $language, $title, $userLogin);
        return $this->generate($html, ['filename' => $filename]);
    }

    /**
     * Construire le HTML pour le PDF des villes
     */
    private function buildVillesHtml(Collection $villes, string $language, string $title, string $userLogin = ''): string
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
            ['fr' => 'Ville', 'ar' => 'المدينة', 'en' => 'City', 'width' => '20%'],
            ['fr' => 'Pays', 'ar' => 'الدولة', 'en' => 'Country', 'width' => '15%'],
            ['fr' => 'Code', 'ar' => 'الكود', 'en' => 'Code', 'width' => '10%'],
            ['fr' => 'Ordre', 'ar' => 'الترتيب', 'en' => 'Order', 'width' => '8%'],
            ['fr' => 'Couleur Fond', 'ar' => 'اللون الخلفي', 'en' => 'Background Color', 'width' => '8%'],
            ['fr' => 'Couleur Texte', 'ar' => 'لون النص', 'en' => 'Text Color', 'width' => '8%'],
            ['fr' => 'Défaut', 'ar' => 'افتراضي', 'en' => 'Default', 'width' => '5%'],
            ['fr' => 'Actif', 'ar' => 'نشط', 'en' => 'Active', 'width' => '5%'],
        ];

        $headers = $this->getTableHeaders($language, $headerLabels);
        $rows = $this->buildVillesRows($villes, $language, $labelKey);

        return $this->buildPdfHtml($title, $language, $headers, $rows, $userLogin);
    }

    /**
     * Construire les lignes du tableau des villes
     */
    private function buildVillesRows(Collection $villes, string $language, string $labelKey): string
    {
        $rows = '';
        
        foreach ($villes as $ville) {
            $isActive = $ville->is_active !== false ? '✓' : '';
            $isDefault = $ville->is_default ? '✓' : '';
            $paysLabel = $ville->pays && $ville->pays->label_fr ? $ville->pays->label_fr : '—';
            $dirStyle = $language === 'ar' ? 'direction: rtl;' : '';
            
            // Créer les boîtes colorées pour bg_color et text_color
            $bgColor = $ville->bg_color ?? '#ffffff';
            $textColor = $ville->text_color ?? '#73879c';
            // Utiliser une cellule HTML simple pour mPDF
            $bgColorBox = '<span style="background-color: ' . $bgColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($bgColor);
            $textColorBox = '<span style="background-color: ' . $textColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($textColor);
            
            $rows .= '<tr>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $ville->id . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; ' . $dirStyle . '">' . htmlspecialchars($ville->$labelKey) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($paysLabel) . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($ville->abbreviation ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($ville->classement ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $bgColorBox . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $textColorBox . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $isDefault . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $isActive . '</td>
                </tr>';
        }
        
        return $rows;
    }
}
