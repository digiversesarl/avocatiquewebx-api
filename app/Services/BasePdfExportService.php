<?php

namespace App\Services;

use Mpdf\Mpdf;
use Illuminate\Support\Facades\Auth;

class BasePdfExportService
{
    protected Mpdf $mpdf;
    protected string $userLogin;

    // Labels i18n centralisés
    private const LABELS = [
        'generated' => [
            'fr' => 'Généré le',
            'en' => 'Generated on',
            'ar' => 'تم إنشاؤه في',
        ],
        'user' => [
            'fr' => 'Utilisateur',
            'en' => 'User',
            'ar' => 'المستخدم',
        ],
        'number' => [
            'fr' => '#',
            'en' => '#',
            'ar' => '#',
        ],
    ];

    public function __construct()
    {
        $user            = Auth::user();
        $this->userLogin = '';
        if ($user) {
            $this->userLogin = isset($user->login) ? $user->login : (isset($user->email) ? $user->email : '');
        }

        $this->mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 15,
            'margin_bottom' => 25,
        ]);
    }

    // -------------------------------------------------------------------------
    // API publique
    // -------------------------------------------------------------------------

    /**
     * Générer un PDF à partir d'HTML et retourner le contenu binaire.
     */
    public function generate(string $html, array $options = []): string
    {
        $this->mpdf->WriteHTML($html);

        $filename = $options['filename'] ?? 'document.pdf';
        return $this->mpdf->Output($filename, 'S');
    }

    // -------------------------------------------------------------------------
    // Construction HTML
    // -------------------------------------------------------------------------

    /**
     * Point d'entrée principal pour construire la page PDF complète.
     */
    protected function buildPdfHtml(
        string $title,
        string $language,
        string $tableHeaders,
        string $tableRows
    ): string {
        $isRtl     = $language === 'ar';
        $dirAttr   = $isRtl ? 'rtl' : 'ltr';
        $textAlign = $isRtl ? 'right' : 'left';

        return '<!DOCTYPE html>
<html dir="' . $dirAttr . '">
<head>
    <meta charset="UTF-8">
    <style>
        ' . $this->buildCss($textAlign) . '
    </style>
</head>
<body>
    ' . $this->buildFooterHtml($language) . '

    <h1>' . htmlspecialchars($title) . '</h1>

    <table>
        <thead>
            <tr>' . $tableHeaders . '</tr>
        </thead>
        <tbody>
            ' . $tableRows . '
        </tbody>
    </table>
</body>
</html>';
    }

    /**
     * Construire les en-têtes du tableau selon la langue.
     *
     * @param  array<int, array{fr: string, en: string, ar: string, width: string}> $headerLabels
     */
    protected function getTableHeaders(string $language, array $headerLabels): string
    {
        $lang = $this->resolveLanguage($language);

        $cells = ['<th style="width: 5%;">' . self::LABELS['number'][$lang] . '</th>'];

        foreach ($headerLabels as $label) {
            $cells[] = '<th style="width: ' . $label['width'] . ';">' . $label[$lang] . '</th>';
        }

        return implode('', $cells);
    }

    // -------------------------------------------------------------------------
    // Footer natif mPDF
    // -------------------------------------------------------------------------

    /**
     * Construire le footer affiché sur TOUTES les pages.
     * Résultat : Généré le 11/04/2026 22:22  |  1 / 8  |  Utilisateur: admin
     */
    protected function buildFooterHtml(string $language): string
    {
        $isRtl    = $language === 'ar';
        $lang     = $this->resolveLanguage($language);
        $datetime = now()->format('d/m/Y H:i');

        $generatedLabel = self::LABELS['generated'][$lang];
        $userLabel      = self::LABELS['user'][$lang];

        // En arabe : sens inversé — utilisateur à gauche, date à droite
        if ($isRtl) {
            $leftText  = $this->userLogin ? $userLabel . ': ' . htmlspecialchars($this->userLogin) : '';
            $rightText = $generatedLabel . ' ' . $datetime;
            $leftAlign = 'right';
            $rightAlign = 'left';
        } else {
            $leftText   = $generatedLabel . ' ' . $datetime;
            $rightText  = $this->userLogin ? $userLabel . ': ' . htmlspecialchars($this->userLogin) : '';
            $leftAlign  = 'left';
            $rightAlign = 'right';
        }

        $cellStyle = 'font-family: Arial, sans-serif; font-size: 9px; color: #7f8c8d;';

        return '
<htmlpagefooter name="myfooter">
    <table style="width: 100%; border-top: 1px solid #bdc3c7; padding-top: 5px;">
        <tr>
            <td style="' . $cellStyle . ' text-align: ' . $leftAlign . '; width: 33%;">
                ' . $leftText . '
            </td>
            <td style="' . $cellStyle . ' text-align: center; width: 34%;">
                {PAGENO} / {nbpg}
            </td>
            <td style="' . $cellStyle . ' text-align: ' . $rightAlign . '; width: 33%;">
                ' . $rightText . '
            </td>
        </tr>
    </table>
</htmlpagefooter>

<sethtmlpagefooter name="myfooter" value="on" />';
    }

    // -------------------------------------------------------------------------
    // Helpers internes
    // -------------------------------------------------------------------------

    /**
     * Retourner la clé de langue normalisée (fr par défaut).
     */
    private function resolveLanguage(string $language): string
    {
        return in_array($language, ['fr', 'en', 'ar'], true) ? $language : 'fr';
    }

    /**
     * Retourner le CSS commun à tous les PDFs.
     */
    private function buildCss(string $textAlign): string
    {
        return '
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
        }
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        thead {
            background-color: #3498db;
            color: white;
        }
        th {
            padding: 10px;
            text-align: ' . $textAlign . ';
            font-weight: bold;
            border: 1px solid #bdc3c7;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        tbody tr:nth-child(even) {
            background-color: #ecf0f1;
        }';
    }
}
