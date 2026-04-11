<?php

namespace App\Services;

use Mpdf\Mpdf;

class BasePdfExportService
{
    protected Mpdf $mpdf;

    public function __construct()
    {
        $this->mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 25,
        ]);
    }

    /**
     * Générer un PDF à partir d'HTML
     */
    public function generate(string $html, array $options = []): string
    {
        $this->mpdf->WriteHTML($html);
        
        $filename = $options['filename'] ?? 'document.pdf';
        return $this->mpdf->Output($filename, 'S');
    }

    /**
     * Obtenir le texte du footer en fonction de la langue
     */
    protected function getFooterText(string $language, string $userLogin = ''): string
    {
        $generatedText = $this->getGeneratedText($language);
        $datetime = now()->format('d/m/Y H:i');
        
        if ($language === 'ar') {
            $userText = $userLogin ? ' | المستخدم: ' . htmlspecialchars($userLogin) : '';
            return $generatedText . ' ' . $datetime . $userText;
        } elseif ($language === 'en') {
            $userText = $userLogin ? ' | User: ' . htmlspecialchars($userLogin) : '';
            return $generatedText . ' ' . $datetime . $userText;
        } else {
            $userText = $userLogin ? ' | Utilisateur: ' . htmlspecialchars($userLogin) : '';
            return $generatedText . ' ' . $datetime . $userText;
        }
    }

    /**
     * Obtenir les en-têtes du tableau en fonction de la langue
     */
    protected function getTableHeaders(string $language, array $headerLabels): string
    {
        $headers = [];
        
        if ($language === 'ar') {
            $headers[] = '<th style="width: 5%;">#</th>';
            foreach ($headerLabels as $label) {
                $headers[] = '<th style="width: ' . $label['width'] . ';">' . $label['ar'] . '</th>';
            }
        } elseif ($language === 'en') {
            $headers[] = '<th style="width: 5%;">#</th>';
            foreach ($headerLabels as $label) {
                $headers[] = '<th style="width: ' . $label['width'] . ';">' . $label['en'] . '</th>';
            }
        } else {
            $headers[] = '<th style="width: 5%;">#</th>';
            foreach ($headerLabels as $label) {
                $headers[] = '<th style="width: ' . $label['width'] . ';">' . $label['fr'] . '</th>';
            }
        }
        
        return implode('', $headers);
    }

    /**
     * Obtenir le texte de génération en fonction de la langue
     */
    protected function getGeneratedText(string $language): string
    {
        if ($language === 'ar') {
            return 'تم إنشاؤه في';
        } elseif ($language === 'en') {
            return 'Generated on';
        } else {
            return 'Généré le';
        }
    }

    /**
     * Construire le HTML du tableau PDF
     */
    protected function buildPdfHtml(
        string $title,
        string $language,
        string $tableHeaders,
        string $tableRows,
        string $userLogin = ''
    ): string {
        $dirAttr = $language === 'ar' ? 'rtl' : 'ltr';
        $textAlign = $language === 'ar' ? 'right' : 'left';
        $footerText = $this->getFooterText($language, $userLogin);

        $htmlTemplate = '<!DOCTYPE html>
            <html dir="' . $dirAttr . '">
            <head>
                <meta charset="UTF-8">
                <style>
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
                    }
                    tbody tr:hover {
                        background-color: #d5dbdb;
                    }
                    .footer {
                        margin-top: 40px;
                        padding-top: 15px;
                        text-align: center;
                        color: #7f8c8d;
                        font-size: 10px;
                        border-top: 1px solid #bdc3c7;
                        page-break-inside: avoid;
                    }
                </style>
            </head>
            <body>
                <h1>' . htmlspecialchars($title) . '</h1>
                <table>
                    <thead>
                        <tr>
                            ' . $tableHeaders . '
                        </tr>
                    </thead>
                    <tbody>
                        ' . $tableRows . '
                    </tbody>
                </table>
                <div class="footer">
                    <p>' . $footerText . '</p>
                </div>
            </body>
            </html>';

        return $htmlTemplate;
    }
}
