<?php

namespace App\Services;

use Illuminate\Support\Collection;

class VillesExportService extends BasePdfExportService
{
    private TranslationService $t;

    public function __construct(TranslationService $t)
    {
        parent::__construct();
        $this->t = $t;
    }

    public function generatePdf(Collection $villes, string $language = 'fr', string $title = '', string $filename = 'villes.pdf'): string
    {
        $title = $title ?: $this->t->get('export.villes.pdf_title', $language, 'Villes');
        $html  = $this->buildVillesHtml($villes, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    private function buildVillesHtml(Collection $villes, string $language, string $title): string
    {
        $labelKey = match ($language) {
            'ar'    => 'label_ar',
            'en'    => 'label_en',
            default => 'label_fr',
        };

        $lbl = $this->t->many([
            'export.villes.label', 'export.pays.label_fr', 'export.pays.code',
            'export.common.order', 'export.common.bg_color', 'export.common.text_color',
            'export.common.default', 'export.user.active',
        ], $language);

        $headerLabels = [
            ['label' => $lbl['export.villes.label'],       'width' => ''],
            ['label' => $lbl['export.pays.label_fr'],      'width' => ''],
            ['label' => $lbl['export.pays.code'],          'width' => '7%'],
            ['label' => $lbl['export.common.order'],       'width' => '7%'],
            ['label' => $lbl['export.common.bg_color'],    'width' => '12%'],
            ['label' => $lbl['export.common.text_color'],  'width' => '12%'],
            ['label' => $lbl['export.common.default'],     'width' => '6%'],
            ['label' => $lbl['export.user.active'],        'width' => '6%'],
        ];

        $headers = $this->getTableHeadersFlat($language, $headerLabels);
        $rows    = $this->buildVillesRows($villes, $language, $labelKey);

        return $this->buildPdfHtml($title, $language, $headers, $rows);
    }

    private function buildVillesRows(Collection $villes, string $language, string $labelKey): string
    {
        $rows = '';

        foreach ($villes as $ville) {
            $isActive  = $ville->is_active !== false ? '✓' : '';
            $isDefault = $ville->is_default ? '✓' : '';
            $paysLabel = $ville->pays && $ville->pays->label_fr ? $ville->pays->label_fr : '—';
            $dirStyle  = $language === 'ar' ? 'direction: rtl;' : '';

            $bgColor      = $ville->bg_color ?? '#ffffff';
            $textColor    = $ville->text_color ?? '#73879c';
            $bgColorBox   = '<span style="background-color: ' . $bgColor . '; padding: 2px 6px; border: 1px solid #999; margin-right: 8px;">&nbsp;&nbsp;&nbsp;</span>' . htmlspecialchars($bgColor);
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
