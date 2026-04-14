<?php

namespace App\Services;

use Illuminate\Support\Collection;

class UserExportService extends BasePdfExportService
{
    /**
     * Générer un PDF pour l'export du personnel
     */
    public function generatePdf(Collection $users, string $language = 'fr', string $filename = 'personnel.pdf'): string
    {
        if ($language === 'en') {
            $title = 'Personnel List';
        } elseif ($language === 'ar') {
            $title = 'قائمة الموظفين';
        } else {
            $title = 'Liste du Personnel';
        }

        $html = $this->buildUsersHtml($users, $language, $title);
        return $this->generate($html, ['filename' => $filename]);
    }

    private function buildUsersHtml(Collection $users, string $language, string $title): string
    {
        $headerLabels = [
            ['fr' => 'Matricule',   'en' => 'ID',         'ar' => 'المعرف',        'width' => '7%'],
            ['fr' => 'Nom complet', 'en' => 'Full name',  'ar' => 'الاسم الكامل',  'width' => ''],
            ['fr' => 'Login',       'en' => 'Login',      'ar' => 'الدخول',        'width' => '10%'],
            ['fr' => 'Rôles',       'en' => 'Roles',      'ar' => 'الأدوار',       'width' => '10%'],
            ['fr' => 'Département', 'en' => 'Department', 'ar' => 'القسم',         'width' => '12%'],
            ['fr' => 'Fonction',    'en' => 'Function',   'ar' => 'الوظيفة',       'width' => '12%'],
            ['fr' => 'Langue',      'en' => 'Language',   'ar' => 'اللغة',         'width' => '7%'],
            ['fr' => 'Email',       'en' => 'Email',      'ar' => 'البريد',        'width' => ''],
            ['fr' => 'Actif',       'en' => 'Active',     'ar' => 'نشط',           'width' => '6%'],
        ];

        $headers = $this->getTableHeaders($language, $headerLabels);
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
