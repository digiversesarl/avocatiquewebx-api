<?php

namespace App\Services;

use Illuminate\Support\Collection;

class UserExportService extends BasePdfExportService
{
    private TranslationService $t;

    public function __construct(TranslationService $t)
    {
        parent::__construct();
        $this->t = $t;
    }

    /**
     * Générer un PDF pour l'export du personnel
     */
    public function generatePdf(Collection $users, string $language = 'fr', string $filename = 'personnel.pdf'): string
    {
        $title = $this->t->get('export.user.pdf_title', $language, 'Liste du Personnel');

        $html = $this->buildUsersHtml($users, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    private function buildUsersHtml(Collection $users, string $language, string $title): string
    {
        $lbl = $this->t->many([
            'export.user.matricule', 'export.user.full_name_fr', 'export.user.login',
            'export.user.roles', 'export.user.departement', 'export.user.fonction',
            'export.user.langue', 'export.user.email', 'export.user.active',
        ], $language);

        $headerLabels = [
            ['label' => $lbl['export.user.matricule'],    'width' => '7%'],
            ['label' => $lbl['export.user.full_name_fr'], 'width' => ''],
            ['label' => $lbl['export.user.login'],        'width' => '10%'],
            ['label' => $lbl['export.user.roles'],        'width' => '10%'],
            ['label' => $lbl['export.user.departement'],  'width' => '12%'],
            ['label' => $lbl['export.user.fonction'],     'width' => '12%'],
            ['label' => $lbl['export.user.langue'],       'width' => '7%'],
            ['label' => $lbl['export.user.email'],        'width' => ''],
            ['label' => $lbl['export.user.active'],       'width' => '6%'],
        ];

        $headers = $this->getTableHeadersFlat($language, $headerLabels);
        $rows    = $this->buildUsersRows($users, $language);

        return $this->buildPdfHtml($title, $language, $headers, $rows);
    }

    private function buildUsersRows(Collection $users, string $language): string
    {
        $rows    = '';
        $isRtl   = $language === 'ar';
        $dirStyle = $isRtl ? 'direction: rtl; text-align: right;' : '';

        foreach ($users as $u) {
            $name    = $isRtl ? ($u->full_name_ar ?: $u->full_name_fr) : $u->full_name_fr;
            $roles   = $u->roles->pluck('name')->implode(', ');
            $langue  = $u->langue === 'fr' ? 'FR' : ($u->langue === 'en' ? 'EN' : 'AR');
            $active  = $u->active ? '✓' : '✗';
            $activeColor = $u->active ? '#27ae60' : '#e74c3c';

            $rows .= '<tr>
                <td style="padding: 5px 7px; border: 1px solid #ddd; text-align: center;">' . $u->id . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd;">' . htmlspecialchars((string) $u->matricule) . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd; ' . $dirStyle . '">' . htmlspecialchars((string) $name) . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd;">' . htmlspecialchars((string) $u->login) . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd;">' . htmlspecialchars($roles) . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($u->departement ?? '')) . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($u->fonction ?? '')) . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd; text-align: center;">' . $langue . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($u->email ?? '')) . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd; text-align: center; color: ' . $activeColor . '; font-weight: bold;">' . $active . '</td>
            </tr>';
        }

        return $rows;
    }
}
