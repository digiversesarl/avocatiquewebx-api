<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DepartementExportService extends BasePdfExportService
{
    public function __construct(private readonly TranslationService $t)
    {
        parent::__construct();
    }

    public function generatePdf(Collection $departements, string $language = 'fr', string $title = '', string $filename = 'departements.pdf'): string
    {
        $title = $title ?: $this->t->get('export.departement.pdf_title', $language, 'Départements');
        $html  = $this->buildDepartementsHtml($departements, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    private function buildDepartementsHtml(Collection $departements, string $language, string $title): string
    {
        $labelKey = match ($language) {
            'ar'    => 'label_ar',
            'en'    => 'label_en',
            default => 'label_fr',
        };

        $lbl = $this->t->many([
            'export.departement.label', 'export.common.abbreviation', 'export.common.order',
            'export.common.bg_color', 'export.common.text_color',
            'export.common.default', 'export.user.active',
        ], $language);

        $headerLabels = [
            ['label' => $lbl['export.departement.label'],      'width' => ''],
            ['label' => $lbl['export.common.abbreviation'],    'width' => '10%'],
            ['label' => $lbl['export.common.order'],           'width' => '8%'],
            ['label' => $lbl['export.common.bg_color'],        'width' => ''],
            ['label' => $lbl['export.common.text_color'],      'width' => ''],
            ['label' => $lbl['export.common.default'],         'width' => '8%'],
            ['label' => $lbl['export.user.active'],            'width' => '8%'],
        ];

        $headers = $this->getTableHeadersFlat($language, $headerLabels);
        $rows    = $this->buildDepartementsRows($departements, $language, $labelKey);

        return $this->buildPdfHtml($title, $language, $headers, $rows);
    }    /**
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
