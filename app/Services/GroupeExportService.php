<?php

namespace App\Services;

use Illuminate\Support\Collection;

class GroupeExportService extends BasePdfExportService
{
    /**
     * Générer un PDF pour l'export de groupes
     */
    public function generatePdf(Collection $groupes, string $language = 'fr', string $title = 'Groupes', string $filename = 'groupes.pdf'): string
    {
        $html = $this->buildGroupesHtml($groupes, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    /**
     * Construire le HTML pour le PDF des groupes
     */
    private function buildGroupesHtml(Collection $groupes, string $language, string $title): string
    {
        if ($language === 'ar') {
            $labelKey = 'label_ar';
        } elseif ($language === 'en') {
            $labelKey = 'label_en';
        } else {
            $labelKey = 'label_fr';
        }

        $headerLabels = [
            ['fr' => 'Groupe',         'en' => 'Group',      'ar' => 'المجموعة',     'width' => ''],
            ['fr' => 'Ordre',          'en' => 'Order',      'ar' => 'الترتيب',      'width' => '8%'],
            ['fr' => 'Couleur fond',   'en' => 'Bg Color',   'ar' => 'اللون الخلفي', 'width' => ''],
            ['fr' => 'Couleur texte',  'en' => 'Text Color', 'ar' => 'لون النص',     'width' => ''],
            ['fr' => 'Défaut',         'en' => 'Default',    'ar' => 'افتراضي',      'width' => '8%'],
            ['fr' => 'Actif',          'en' => 'Active',     'ar' => 'نشط',          'width' => '8%'],
        ];

        $headers = $this->getTableHeaders($language, $headerLabels);
        $rows = $this->buildGroupesRows($groupes, $language, $labelKey);

        return $this->buildPdfHtml($title, $language, $headers, $rows);
    }

    /**
     * Construire les lignes du tableau des groupes
     */
    private function buildGroupesRows(Collection $groupes, string $language, string $labelKey): string
    {
        $rows = '';

        foreach ($groupes as $groupe) {
            $isActive  = $groupe->is_active !== false ? '✓' : '';
            $isDefault = $groupe->is_default ? '✓' : '';
            $dirStyle  = $language === 'ar' ? 'direction: rtl;' : '';

            $bgColor    = $groupe->bg_color ?? '#ffffff';
            $textColor  = $groupe->text_color ?? '#73879c';
            $bgColorBox   = '<span style="background-color: ' . $bgColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($bgColor);
            $textColorBox = '<span style="background-color: ' . $textColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($textColor);

            $rows .= '<tr>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $groupe->id . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; ' . $dirStyle . '">' . htmlspecialchars($groupe->$labelKey ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($groupe->classement ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $bgColorBox . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $textColorBox . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $isDefault . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $isActive . '</td>
                </tr>';
        }

        return $rows;
    }
}
