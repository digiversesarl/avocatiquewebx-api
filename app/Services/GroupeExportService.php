<?php

namespace App\Services;

use Illuminate\Support\Collection;

class GroupeExportService extends BasePdfExportService
{
    public function __construct(private readonly TranslationService $t)
    {
        parent::__construct();
    }

    public function generatePdf(Collection $groupes, string $language = 'fr', string $title = '', string $filename = 'groupes.pdf'): string
    {
        $title = $title ?: $this->t->get('export.groupe.pdf_title', $language, 'Groupes');
        $html  = $this->buildGroupesHtml($groupes, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    private function buildGroupesHtml(Collection $groupes, string $language, string $title): string
    {
        $labelKey = match ($language) {
            'ar'    => 'label_ar',
            'en'    => 'label_en',
            default => 'label_fr',
        };

        $lbl = $this->t->many([
            'export.groupe.label', 'export.common.order',
            'export.common.bg_color', 'export.common.text_color',
            'export.common.default', 'export.user.active',
        ], $language);

        $headerLabels = [
            ['label' => $lbl['export.groupe.label'],       'width' => ''],
            ['label' => $lbl['export.common.order'],       'width' => '8%'],
            ['label' => $lbl['export.common.bg_color'],    'width' => ''],
            ['label' => $lbl['export.common.text_color'],  'width' => ''],
            ['label' => $lbl['export.common.default'],     'width' => '8%'],
            ['label' => $lbl['export.user.active'],        'width' => '8%'],
        ];

        $headers = $this->getTableHeadersFlat($language, $headerLabels);
        $rows    = $this->buildGroupesRows($groupes, $language, $labelKey);

        return $this->buildPdfHtml($title, $language, $headers, $rows);
    }

    private function buildGroupesRows(Collection $groupes, string $language, string $labelKey): string
    {
        $rows = '';

        foreach ($groupes as $groupe) {
            $isActive  = $groupe->is_active !== false ? '✓' : '';
            $isDefault = $groupe->is_default ? '✓' : '';
            $dirStyle  = $language === 'ar' ? 'direction: rtl;' : '';

            $bgColor      = $groupe->bg_color ?? '#ffffff';
            $textColor    = $groupe->text_color ?? '#73879c';
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
