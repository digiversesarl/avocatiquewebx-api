<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AuditExportService extends BasePdfExportService
{
    // ── Labels trilingues ───────────────────────────────────────────
    private const TITLES = [
        'fr' => 'Journal d\'audit & sécurité',
        'en' => 'Audit & Security Log',
        'ar' => 'سجل المراجعة والأمان',
    ];

    private const HEADERS = [
        ['fr' => 'Date',           'en' => 'Date',        'ar' => 'التاريخ',       'width' => '14%'],
        ['fr' => 'Utilisateur',    'en' => 'User',        'ar' => 'المستخدم',      'width' => '14%'],
        ['fr' => 'Action',         'en' => 'Action',      'ar' => 'الإجراء',       'width' => '14%'],
        ['fr' => 'Catégorie',      'en' => 'Category',    'ar' => 'الفئة',         'width' => '10%'],
        ['fr' => 'Résultat',       'en' => 'Result',      'ar' => 'النتيجة',       'width' => '8%'],
        ['fr' => 'Modèle',         'en' => 'Model',       'ar' => 'النموذج',       'width' => '10%'],
        ['fr' => 'Enregistrement', 'en' => 'Record',      'ar' => 'السجل',         'width' => '14%'],
        ['fr' => 'IP',             'en' => 'IP',          'ar' => 'IP',             'width' => '10%'],
        ['fr' => 'Description',    'en' => 'Description', 'ar' => 'الوصف',         'width' => ''],
    ];

    // ── XLSX Export ─────────────────────────────────────────────────

    /**
     * Générer un fichier XLSX en mémoire et retourner le contenu binaire.
     */
    public function generateXlsx(Builder $query, string $language = 'fr'): string
    {
        $lang = in_array($language, ['fr', 'en', 'ar'], true) ? $language : 'fr';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->truncateSheetTitle(self::TITLES[$lang]));

        // RTL for Arabic
        if ($lang === 'ar') {
            $sheet->setRightToLeft(true);
        }

        // ── Title row ──
        $sheet->setCellValue('A1', self::TITLES[$lang]);
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '2C3E50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ── Sub-title (date) ──
        $sheet->setCellValue('A2', ($lang === 'ar' ? 'تم إنشاؤه في' : ($lang === 'en' ? 'Generated on' : 'Généré le')) . ' ' . now()->format('d/m/Y H:i'));
        $sheet->mergeCells('A2:I2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '7F8C8D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ── Headers (row 4) ──
        $colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        foreach (self::HEADERS as $i => $h) {
            $sheet->setCellValue($colLetters[$i] . '4', $h[$lang]);
        }
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3498DB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BDC3C7']]],
        ];
        $sheet->getStyle('A4:I4')->applyFromArray($headerStyle);
        $sheet->getRowDimension(4)->setRowHeight(22);

        // ── Data rows ──
        $row = 5;
        $query->chunk(500, function ($logs) use ($sheet, &$row) {
            foreach ($logs as $log) {
                $sheet->setCellValue('A' . $row, $log->created_at->format('Y-m-d H:i:s'));
                $sheet->setCellValue('B' . $row, $log->user_name);
                $sheet->setCellValue('C' . $row, $log->action);
                $sheet->setCellValue('D' . $row, $log->category);
                $sheet->setCellValue('E' . $row, $log->result);
                $sheet->setCellValue('F' . $row, $log->auditable_type ? class_basename($log->auditable_type) : '');
                $sheet->setCellValue('G' . $row, $log->auditable_label ?? '');
                $sheet->setCellValue('H' . $row, $log->ip_address ?? '');
                $sheet->setCellValue('I' . $row, $log->description ?? '');
                $row++;
            }
        });

        // ── Zebra striping ──
        $lastRow = max($row - 1, 5);
        for ($r = 5; $r <= $lastRow; $r++) {
            if ($r % 2 === 0) {
                $sheet->getStyle("A{$r}:I{$r}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F5F5F5');
            }
        }

        // ── Borders on data ──
        if ($lastRow >= 5) {
            $sheet->getStyle("A5:I{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
                'font'    => ['size' => 9],
            ]);
        }

        // ── Auto-size columns ──
        foreach ($colLetters as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Write to memory ──
        $writer = new Xlsx($spreadsheet);
        $tmpFile = tempnam(sys_get_temp_dir(), 'audit_xlsx_');
        $writer->save($tmpFile);
        $content = file_get_contents($tmpFile);
        unlink($tmpFile);
        $spreadsheet->disconnectWorksheets();

        return $content;
    }

    // ── PDF Export ──────────────────────────────────────────────────

    /**
     * Générer un PDF des logs d'audit et retourner le contenu binaire.
     * Format A4 paysage avec colonnes optimisées.
     */
    public function generatePdf(Builder $query, string $language = 'fr', string $filename = 'audit-logs.pdf'): string
    {
        $lang = in_array($language, ['fr', 'en', 'ar'], true) ? $language : 'fr';
        $title = self::TITLES[$lang];
        $isRtl = $lang === 'ar';

        // Reconfigure mPDF for landscape — disable simpleTables for proper column sizing
        $tmpDir = storage_path('app/mpdf_tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $this->mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4-L',
            'margin_left'   => 7,
            'margin_right'  => 7,
            'margin_top'    => 15,
            'margin_bottom' => 22,
            'tempDir'       => $tmpDir,
            'compress'      => true,
            'simpleTables'  => false,
            'packTableData' => true,
        ]);

        $logs = $query->get();
        $html = $this->buildAuditPdfHtml($logs, $lang, $title, $isRtl);

        return $this->generate($html, [
            'filename' => $filename,
        ]);
    }

    /**
     * Construire le HTML complet du PDF audit avec colonnes optimisées.
     * A4-L = 297mm – 14mm marges = 283mm utile.
     */
    private function buildAuditPdfHtml($logs, string $lang, string $title, bool $isRtl): string
    {
        $dir      = $isRtl ? 'rtl' : 'ltr';
        $align    = $isRtl ? 'right' : 'left';
        $datetime = now()->format('d/m/Y H:i');

        $genLabel  = ['fr' => 'Généré le', 'en' => 'Generated on', 'ar' => 'تم إنشاؤه في'][$lang];
        $userLabel = ['fr' => 'Utilisateur', 'en' => 'User', 'ar' => 'المستخدم'][$lang];

        // Column headers with fixed widths (total ≈ 283mm)
        $cols = [
            ['label' => '#',                             'w' => '14mm'],
            ['label' => self::HEADERS[0][$lang],         'w' => '38mm'],  // Date
            ['label' => self::HEADERS[1][$lang],         'w' => '32mm'],  // Utilisateur
            ['label' => self::HEADERS[2][$lang],         'w' => '32mm'],  // Action
            ['label' => self::HEADERS[3][$lang],         'w' => '22mm'],  // Catégorie
            ['label' => self::HEADERS[4][$lang],         'w' => '18mm'],  // Résultat
            ['label' => self::HEADERS[5][$lang],         'w' => '24mm'],  // Modèle
            ['label' => self::HEADERS[6][$lang],         'w' => '40mm'],  // Enregistrement
            ['label' => self::HEADERS[7][$lang],         'w' => '24mm'],  // IP
            ['label' => self::HEADERS[8][$lang],         'w' => '39mm'],  // Description
        ];

        // Build header cells
        $thCells = '';
        foreach ($cols as $c) {
            $thCells .= '<th style="width: ' . $c['w'] . ';">' . $c['label'] . '</th>';
        }

        // Build data rows
        $rowsHtml = '';
        $dirStyle = $isRtl ? ' direction: rtl;' : '';
        foreach ($logs as $i => $log) {
            $bg = ($i % 2 === 1) ? ' background-color: #f9f9f9;' : '';
            $resultBadge = $log->result === 'success'
                ? '<span style="color: #27ae60; font-weight: bold;">✓</span>'
                : '<span style="color: #e74c3c; font-weight: bold;">✗</span>';
            $model = $log->auditable_type ? class_basename($log->auditable_type) : '';

            $rowsHtml .= '<tr style="' . $bg . '">
                <td style="text-align: center;">' . $log->id . '</td>
                <td style="white-space: nowrap;">' . $log->created_at->format('Y-m-d H:i') . '</td>
                <td style="' . $dirStyle . '">' . htmlspecialchars($log->user_name) . '</td>
                <td>' . htmlspecialchars($log->action) . '</td>
                <td>' . htmlspecialchars($log->category) . '</td>
                <td style="text-align: center;">' . $resultBadge . '</td>
                <td>' . htmlspecialchars($model) . '</td>
                <td style="' . $dirStyle . '">' . htmlspecialchars($log->auditable_label ?? '') . '</td>
                <td style="font-family: monospace;">' . htmlspecialchars($log->ip_address ?? '') . '</td>
                <td style="' . $dirStyle . '">' . htmlspecialchars($log->description ?? '') . '</td>
            </tr>';
        }

        $userLogin = $this->userLogin ? htmlspecialchars($this->userLogin) : '';

        return '<!DOCTYPE html>
<html dir="' . $dir . '">
<head><meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #333; }
h1 { text-align: center; color: #2c3e50; margin: 0 0 12px 0; font-size: 16px; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th { padding: 5px 6px; text-align: ' . $align . '; font-weight: bold; border: 1px solid #bdc3c7;
     background-color: #3498db; color: #fff; font-size: 9px; white-space: nowrap; }
td { padding: 4px 6px; border: 1px solid #ddd; font-size: 9px; word-break: break-word; overflow: hidden; }
</style>
</head>
<body>
<htmlpagefooter name="myfooter">
<table style="width:100%; border-top:1px solid #bdc3c7; padding-top:4px; border:none;">
<tr>
  <td style="border:none; font-size:8px; color:#7f8c8d; text-align:' . ($isRtl ? 'right' : 'left') . '; width:33%;">' . $genLabel . ' ' . $datetime . '</td>
  <td style="border:none; font-size:8px; color:#7f8c8d; text-align:center; width:34%;">{PAGENO} / {nbpg}</td>
  <td style="border:none; font-size:8px; color:#7f8c8d; text-align:' . ($isRtl ? 'left' : 'right') . '; width:33%;">' . ($userLogin ? $userLabel . ': ' . $userLogin : '') . '</td>
</tr>
</table>
</htmlpagefooter>
<sethtmlpagefooter name="myfooter" value="on" />

<h1>' . htmlspecialchars($title) . '</h1>
<table>
  <thead><tr>' . $thCells . '</tr></thead>
  <tbody>' . $rowsHtml . '</tbody>
</table>
</body></html>';
    }

    /**
     * Construire les lignes HTML du tableau des logs (legacy, utilisé par buildPdfHtml).
     */
    private function buildAuditRows($logs, string $language): string
    {
        $isRtl = $language === 'ar';
        $dirStyle = $isRtl ? 'direction: rtl;' : '';
        $rows = '';

        foreach ($logs as $log) {
            $resultBadge = $log->result === 'success'
                ? '<span style="color: #27ae60; font-weight: bold;">✓</span>'
                : '<span style="color: #e74c3c; font-weight: bold;">✗</span>';

            $model = $log->auditable_type ? class_basename($log->auditable_type) : '';

            $rows .= '<tr>
                <td style="padding: 5px 7px; border: 1px solid #ddd; text-align: center;">' . $log->id . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd; white-space: nowrap; font-size: 8px;">' . $log->created_at->format('Y-m-d H:i:s') . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd; ' . $dirStyle . '">' . htmlspecialchars($log->user_name) . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd;">' . htmlspecialchars($log->action) . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd;">' . htmlspecialchars($log->category) . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd; text-align: center;">' . $resultBadge . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd;">' . htmlspecialchars($model) . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd; ' . $dirStyle . '">' . htmlspecialchars($log->auditable_label ?? '') . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd; font-family: monospace; font-size: 8px;">' . htmlspecialchars($log->ip_address ?? '') . '</td>
                <td style="padding: 5px 7px; border: 1px solid #ddd; font-size: 8px; ' . $dirStyle . '">' . htmlspecialchars($log->description ?? '') . '</td>
            </tr>';
        }

        return $rows;
    }

    /**
     * Tronquer un titre de feuille Excel à 31 caractères (limite Excel).
     */
    private function truncateSheetTitle(string $title): string
    {
        return mb_substr($title, 0, 31);
    }
}
