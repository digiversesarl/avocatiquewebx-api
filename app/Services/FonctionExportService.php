<?php

namespace App\Services;

use Illuminate\Support\Collection;

class FonctionExportService extends BasePdfExportService
{
    private TranslationService $t;

    public function __construct(TranslationService $t)
    {
        parent::__construct();
        $this->t = $t;
    }

    public function generatePdf(Collection $fonctions, string $language = 'fr', string $title = '', string $filename = 'fonctions.pdf'): string
    {
        $title = $title ?: $this->t->get('export.fonction.pdf_title', $language, 'Fonctions');
        $html  = $this->buildFonctionsHtml($fonctions, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    private function buildFonctionsHtml(Collection $fonctions, string $language, string $title): string
    {
        $labelKey = match ($language) {
            'ar'    => 'label_ar',
            'en'    => 'label_en',
            default => 'label_fr',
        };

        $lbl = $this->t->many([
            'export.fonction.label', 'export.pays.code', 'export.common.order',
            'export.common.bg_color', 'export.common.text_color',
            'export.common.default', 'export.user.active',
        ], $language);

        $headerLabels = [
            ['label' => $lbl['export.fonction.label'],     'width' => ''],
            ['label' => $lbl['export.pays.code'],          'width' => '8%'],
            ['label' => $lbl['export.common.order'],       'width' => '8%'],
            ['label' => $lbl['export.common.bg_color'],    'width' => ''],
            ['label' => $lbl['export.common.text_color'],  'width' => ''],
            ['label' => $lbl['export.common.default'],     'width' => '8%'],
            ['label' => $lbl['export.user.active'],        'width' => '8%'],
        ];

        $headers = $this->getTableHeadersFlat($language, $headerLabels);
        $rows    = $this->buildFonctionsRows($fonctions, $language, $labelKey);

        return $this->buildPdfHtml($title, $language, $headers, $rows);
    }

    private function buildFonctionsRows(Collection $fonctions, string $language, string $labelKey): string
    {
        $rows = '';

        foreach ($fonctions as $fonction) {
            $isActive  = $fonction->is_active !== false ? '✓' : '';
            $isDefault = $fonction->is_default ? '✓' : '';
            $dirStyle  = $language === 'ar' ? 'direction: rtl;' : '';

            $bgColor      = $fonction->bg_color ?? '#ffffff';
            $textColor    = $fonction->text_color ?? '#73879c';
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
