<?php

namespace App\Services;

use Illuminate\Support\Collection;

class GradeExportService extends BasePdfExportService
{
    /**
     * Générer un PDF pour l'export de grades
     */
    public function generatePdf(Collection $grades, string $language = 'fr', string $title = 'Grades', string $filename = 'grades.pdf'): string
    {
        $html = $this->buildGradesHtml($grades, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    /**
     * Construire le HTML pour le PDF des grades
     */
    private function buildGradesHtml(Collection $grades, string $language, string $title): string
    {
        if ($language === 'ar') {
            $labelKey = 'label_ar';
        } elseif ($language === 'en') {
            $labelKey = 'label_en';
        } else {
            $labelKey = 'label_fr';
        }

        $headerLabels = [
            ['fr' => 'Grade',         'en' => 'Grade',    'ar' => 'الدرجة',      'width' => ''],
            ['fr' => 'Ordre',         'en' => 'Order',    'ar' => 'الترتيب',     'width' => '8%'],
            ['fr' => 'Couleur fond',  'en' => 'Bg Color', 'ar' => 'اللون الخلفي','width' => ''],
            ['fr' => 'Couleur texte', 'en' => 'Text Color','ar' => 'لون النص',   'width' => ''],
            ['fr' => 'Défaut',        'en' => 'Default',  'ar' => 'افتراضي',     'width' => '8%'],
            ['fr' => 'Actif',         'en' => 'Active',   'ar' => 'نشط',         'width' => '8%'],
        ];

        $headers = $this->getTableHeaders($language, $headerLabels);
        $rows = $this->buildGradesRows($grades, $language, $labelKey);

        return $this->buildPdfHtml($title, $language, $headers, $rows);
    }

    /**
     * Construire les lignes du tableau des grades
     */
    private function buildGradesRows(Collection $grades, string $language, string $labelKey): string
    {
        $rows = '';

        foreach ($grades as $grade) {
            $isActive  = $grade->is_active !== false ? '✓' : '';
            $isDefault = $grade->is_default ? '✓' : '';
            $dirStyle  = $language === 'ar' ? 'direction: rtl;' : '';

            $bgColor    = $grade->bg_color ?? '#ffffff';
            $textColor  = $grade->text_color ?? '#73879c';
            $bgColorBox   = '<span style="background-color: ' . $bgColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($bgColor);
            $textColorBox = '<span style="background-color: ' . $textColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($textColor);

            $rows .= '<tr>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $grade->id . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; ' . $dirStyle . '">' . htmlspecialchars($grade->$labelKey ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($grade->classement ?? '') . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $bgColorBox . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $textColorBox . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $isDefault . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $isActive . '</td>
                </tr>';
        }

        return $rows;
    }
}
