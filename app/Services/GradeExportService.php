<?php

namespace App\Services;

use Illuminate\Support\Collection;

class GradeExportService extends BasePdfExportService
{
    private TranslationService $t;

    public function __construct(TranslationService $t)
    {
        parent::__construct();
        $this->t = $t;
    }

    public function generatePdf(Collection $grades, string $language = 'fr', string $title = '', string $filename = 'grades.pdf'): string
    {
        $title = $title ?: $this->t->get('export.grade.pdf_title', $language, 'Grades');
        $html  = $this->buildGradesHtml($grades, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    private function buildGradesHtml(Collection $grades, string $language, string $title): string
    {
        $labelKey = match ($language) {
            'ar'    => 'label_ar',
            'en'    => 'label_en',
            default => 'label_fr',
        };

        $lbl = $this->t->many([
            'export.grade.label', 'export.common.order',
            'export.common.bg_color', 'export.common.text_color',
            'export.common.default', 'export.user.active',
        ], $language);

        $headerLabels = [
            ['label' => $lbl['export.grade.label'],        'width' => ''],
            ['label' => $lbl['export.common.order'],       'width' => '8%'],
            ['label' => $lbl['export.common.bg_color'],    'width' => ''],
            ['label' => $lbl['export.common.text_color'],  'width' => ''],
            ['label' => $lbl['export.common.default'],     'width' => '8%'],
            ['label' => $lbl['export.user.active'],        'width' => '8%'],
        ];

        $headers = $this->getTableHeadersFlat($language, $headerLabels);
        $rows    = $this->buildGradesRows($grades, $language, $labelKey);

        return $this->buildPdfHtml($title, $language, $headers, $rows);
    }

    private function buildGradesRows(Collection $grades, string $language, string $labelKey): string
    {
        $rows = '';

        foreach ($grades as $grade) {
            $isActive  = $grade->is_active !== false ? '✓' : '';
            $isDefault = $grade->is_default ? '✓' : '';
            $dirStyle  = $language === 'ar' ? 'direction: rtl;' : '';

            $bgColor      = $grade->bg_color ?? '#ffffff';
            $textColor    = $grade->text_color ?? '#73879c';
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
