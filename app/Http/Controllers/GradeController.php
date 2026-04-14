<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Grade;
use App\Services\GradeExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GradeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Grade::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $s = '%' . $request->search . '%';
                $q->where('label_fr', 'like', $s)
                  ->orWhere('label_ar', 'like', $s)
                  ->orWhere('label_en', 'like', $s);
            }))
            ->when($request->filled('status'), function ($q) use ($request) {
                if ($request->status === 'active') {
                    return $q->where('is_active', true);
                } elseif ($request->status === 'inactive') {
                    return $q->where('is_active', false);
                }
                return $q;
            })
            ->orderBy('classement')
            ->orderBy('id');

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label_fr'   => 'required|string|max:255',
            'label_ar'   => 'required|string|max:255',
            'label_en'   => 'nullable|string|max:255',
            'classement' => 'nullable|integer',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'bg_color'   => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
        ]);

        $grade = Grade::create($data);

        return response()->json($grade, 201);
    }

    public function show(Grade $grade): JsonResponse
    {
        return response()->json($grade);
    }

    public function update(Request $request, Grade $grade): JsonResponse
    {
        $data = $request->validate([
            'label_fr'   => 'required|string|max:255',
            'label_ar'   => 'required|string|max:255',
            'label_en'   => 'nullable|string|max:255',
            'classement' => 'nullable|integer',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'bg_color'   => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
        ]);

        $grade->update($data);

        return response()->json($grade);
    }

    public function destroy(Grade $grade): JsonResponse
    {
        $grade->delete();

        return response()->json(['message' => 'Grade supprimé.']);
    }

    public function toggleActive(Grade $grade): JsonResponse
    {
        $grade->update(['is_active' => !$grade->is_active]);

        return response()->json($grade);
    }

    public function duplicate(Grade $grade): JsonResponse
    {
        $copy = $grade->replicate();
        $copy->label_fr = $grade->label_fr . ' (copie)';
        $copy->classement = Grade::max('classement') + 1;
        $copy->save();

        return response()->json($copy, 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items'   => 'required|array',
            'items.*.id' => 'required|integer|exists:grades,id',
            'items.*.classement' => 'required|integer',
        ]);

        AuditLog::auditReorder(Grade::class, $request->items, 'classement');

        return response()->json(['message' => 'Ordre mis à jour.']);
    }

    public function exportPdf(Request $request, GradeExportService $exportService): Response
    {
        $language = $request->query('lang', 'fr');
        $grades = Grade::orderBy('classement')->get();

        $titles = [
            'ar' => 'قائمة الدرجات',
            'en' => 'Grades List',
            'fr' => 'Liste des Grades',
        ];
        $title = $titles[$language] ?? $titles['fr'];

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "grades_{$timestamp}.pdf";

        $count = $grades->count();
        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'PDF', 'source' => 'Grades', 'count' => $count],
            'success',
            'Export PDF — Grades (' . $count . ' enregistrements)',
            null,
            'Grades — PDF (' . $count . ')'
        );

        $pdf = $exportService->generatePdf($grades, $language, $title, $filename);

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $grades = Grade::orderBy('classement')->get();
        $count = $grades->count();

        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'Excel', 'source' => 'Grades', 'count' => $count],
            'success',
            'Export Excel — Grades (' . $count . ' enregistrements)',
            null,
            'Grades — Excel (' . $count . ')'
        );

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Grades');

        $headers = ['#', 'Label FR', 'Label AR', 'Label EN', 'Classement', 'Couleur fond', 'Couleur texte', 'Défaut', 'Actif'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = $sheet->getStyle('A1:I1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $headerStyle->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($grades as $item) {
            $sheet->fromArray([
                $item->id,
                $item->label_fr,
                $item->label_ar,
                $item->label_en ?? '',
                $item->classement,
                $item->bg_color ?? '',
                $item->text_color ?? '',
                $item->is_default ? 'Oui' : 'Non',
                $item->is_active !== false ? 'Oui' : 'Non',
            ], null, 'A' . $row);
            $row++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'grades_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return new StreamedResponse(function () use ($writer): void {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $grades = Grade::orderBy('classement')->get();
        $count = $grades->count();

        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'CSV', 'source' => 'Grades', 'count' => $count],
            'success',
            'Export CSV — Grades (' . $count . ' enregistrements)',
            null,
            'Grades — CSV (' . $count . ')'
        );

        $filename = 'grades_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($grades) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['#', 'Label FR', 'Label AR', 'Label EN', 'Classement', 'Couleur fond', 'Couleur texte', 'Défaut', 'Actif']);

            foreach ($grades as $grade) {
                fputcsv($handle, [
                    $grade->id,
                    $grade->label_fr,
                    $grade->label_ar,
                    $grade->label_en ?? '',
                    $grade->classement,
                    $grade->bg_color ?? '',
                    $grade->text_color ?? '',
                    $grade->is_default ? 'Oui' : 'Non',
                    $grade->is_active !== false ? 'Oui' : 'Non',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}
