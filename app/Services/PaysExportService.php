<?php

namespace App\Services;

use Illuminate\Support\Collection;

class PaysExportService extends BasePdfExportService
{
     private TranslationService $t;

    public function __construct(TranslationService $t)
    {
        parent::__construct();
        $this->t = $t;
    }

    public function generatePdf(Collection $pays, string $language = 'fr', string $title = '', string $filename = 'pays.pdf'): string
    {
        $title = $title ?: $this->t->get('export.pays.pdf_title', $language, 'Pays');
        $html  = $this->buildPaysHtml($pays, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    private function buildPaysHtml(Collection $pays, string $language, string $title): string
    {
        $labelKey = match ($language) {
            'ar'    => 'label_ar',
            'en'    => 'label_en',
            default => 'label_fr',
        };

        $lbl = $this->t->many([
            'export.pays.label_fr', 'export.pays.code', 'export.common.order',
            'export.common.bg_color', 'export.common.text_color',
            'export.common.default', 'export.pays.active',
        ], $language);

        $headerLabels = [
            ['label' => $lbl['export.pays.label_fr'],     'width' => ''],
            ['label' => $lbl['export.pays.code'],         'width' => '8%'],
            ['label' => $lbl['export.common.order'],      'width' => '8%'],
            ['label' => $lbl['export.common.bg_color'],   'width' => ''],
            ['label' => $lbl['export.common.text_color'], 'width' => ''],
            ['label' => $lbl['export.common.default'],    'width' => '8%'],
            ['label' => $lbl['export.pays.active'],       'width' => '8%'],
        ];

        $headers = $this->getTableHeadersFlat($language, $headerLabels);
        $rows    = $this->buildPaysRows($pays, $language, $labelKey);

        return $this->buildPdfHtml($title, $language, $headers, $rows);
    }

    private function buildPaysRows(Collection $pays, string $language, string $labelKey): string
    {
        $rows = '';

        foreach ($pays as $country) {
            $isActive  = $country->is_active !== false ? '✓' : '';
            $isDefault = $country->is_default ? '✓' : '';
            $dirStyle  = $language === 'ar' ? 'direction: rtl;' : '';

            $bgColor      = $country->bg_color ?? '#ffffff';
            $textColor    = $country->text_color ?? '#73879c';
            $bgColorBox   = '<span style="background-color: ' . $bgColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($bgColor);
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
