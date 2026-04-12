<?php

namespace App\Services;

use Mpdf\Mpdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BasePdfExportService
{
    protected Mpdf $mpdf;
    protected string $userLogin;

    /**
     * Cache base64 par chemin absolu : ['chemin' => 'base64...']
     * Évite les relectures disque répétées dans le même cycle PHP.
     *
     * @var array<string, string>
     */
    private static array $watermarkCache = [];

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
            $this->userLogin = isset($user->login)
                ? $user->login
                : (isset($user->email) ? $user->email : '');
        }

        $tmpDir = storage_path('app/mpdf_tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $this->mpdf = new Mpdf([
            'mode'             => 'utf-8',
            'format'           => 'A4',
            'margin_left'      => 10,
            'margin_right'     => 10,
            'margin_top'       => 15,
            'margin_bottom'    => 25,
            'tempDir'          => $tmpDir,

            // ── Performances ─────────────────────────────────────────
            'compress'            => true,
            'simpleTables'        => true,   // ⚠ incompatible rowspan/colspan
            'packTableData'       => true,
            'useSubstitutions'    => false,
            'use_AdobeCFF'        => false,
            'useActiveForms'      => false,
            'enableImports'       => false,
            'autoScriptToLang'    => false,
            'autoLangToScript'    => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // API publique
    // -------------------------------------------------------------------------

    /**
     * Générer un PDF à partir d'HTML et retourner le contenu binaire.
     *
     * Options :
     *   'filename'        => string        (défaut : 'document.pdf')
     *   'watermark_image' => string|null   null = désactiver explicitement
     *   'watermark_alpha' => float         0.0 (opaque) – 1.0 (invisible), défaut 0.2
     *   'watermark_size'  => mixed         'D' | 'P' | [w, h] en mm
     *   'watermark_pos'   => array         [x, y] en mm, défaut [5, 5]
     */
    public function generate(string $html, array $options = []): string
    {
        $this->applyWatermark($options);

        // ⚠ WriteHTML() sans second argument = parsing HTML complet (correct)
        $this->mpdf->WriteHTML($html);

        $filename = $options['filename'] ?? 'document.pdf';
        return $this->mpdf->Output($filename, 'S');
    }

    // -------------------------------------------------------------------------
    // Filigrane
    // -------------------------------------------------------------------------

    /**
     * Configurer le filigrane image avant génération.
     *
     * Priorité :
     *   1. $options['watermark_image'] explicite  (null = désactivé)
     *   2. config('pdf.watermark_image')
     *   3. public_path('images/filigrane.png')
     */
    protected function applyWatermark(array $options): void
    {
        // Désactivation explicite via null
        if (array_key_exists('watermark_image', $options) && $options['watermark_image'] === null) {
            return;
        }

        $path = $options['watermark_image']
            ?? config('pdf.watermark_image')
            ?? public_path('images/filigrane.png');

        if (!file_exists($path) || !is_readable($path)) {
            Log::warning("BasePdfExportService : filigrane introuvable [{$path}]");
            return;
        }

        // Cache indexé par chemin — supporte plusieurs images différentes
        if (!isset(self::$watermarkCache[$path])) {
            self::$watermarkCache[$path] = base64_encode(file_get_contents($path));
        }

        $mime    = mime_content_type($path);
        $dataUri = "data:{$mime};base64," . self::$watermarkCache[$path];

        $this->mpdf->SetWatermarkImage(
            $dataUri,
            $options['watermark_alpha'] ?? config('pdf.watermark_alpha', 0.2),
            $options['watermark_size']  ?? config('pdf.watermark_size',  'D'),
            $options['watermark_pos']   ?? config('pdf.watermark_pos',   [5, 5])
        );

        $this->mpdf->showWatermarkImage = true;
    }

    // -------------------------------------------------------------------------
    // Construction HTML
    // -------------------------------------------------------------------------

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
     * @param  array<int, array{fr: string, en: string, ar: string, width: string}> $headerLabels
     */
    protected function getTableHeaders(string $language, array $headerLabels): string
    {
        $lang  = $this->resolveLanguage($language);
        $cells = ['<th style="width: 5%;">' . self::LABELS['number'][$lang] . '</th>'];

        foreach ($headerLabels as $label) {
            $cells[] = '<th style="width: ' . $label['width'] . ';">' . $label[$lang] . '</th>';
        }

        return implode('', $cells);
    }

    // -------------------------------------------------------------------------
    // Footer natif mPDF
    // -------------------------------------------------------------------------

    protected function buildFooterHtml(string $language): string
    {
        $isRtl    = $language === 'ar';
        $lang     = $this->resolveLanguage($language);
        $datetime = now()->format('d/m/Y H:i');

        $generatedLabel = self::LABELS['generated'][$lang];
        $userLabel      = self::LABELS['user'][$lang];

        if ($isRtl) {
            $leftText   = $this->userLogin ? $userLabel . ': ' . htmlspecialchars($this->userLogin) : '';
            $rightText  = $generatedLabel . ' ' . $datetime;
            $leftAlign  = 'right';
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

    private function resolveLanguage(string $language): string
    {
        return in_array($language, ['fr', 'en', 'ar'], true) ? $language : 'fr';
    }

    private function buildCss(string $textAlign): string
    {
        return '
        * { margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #333;
        }
        h1 {
            text-align: center;
            color: #2c3e50;
            margin: 0 0 20px 0;
            font-size: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        thead {
            background-color: #3498db;
            color: #fff;
        }
        th {
            padding: 10px;
            text-align: ' . $textAlign . ';
            font-weight: bold;
            border: 1px solid #bdc3c7;
            background-color: #3498db;
            color: white !important;
            font-size: 10px;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        tbody tr:nth-child(2n) {
            background-color: #f5f5f5;
        }';
    }
}