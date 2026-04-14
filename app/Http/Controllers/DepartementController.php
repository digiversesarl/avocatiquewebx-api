<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Departement;
use App\Services\DepartementExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DepartementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Departement::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $s = '%' . $request->search . '%';
                $q->where('label_fr', 'like', $s)
                  ->orWhere('label_ar', 'like', $s)
                  ->orWhere('label_en', 'like', $s)
                  ->orWhere('abbreviation', 'like', $s);
            }))
            ->when($request->filled('status'), fn ($q) => $request->status === 'active' ? $q->where('is_active', true) : ($request->status === 'inactive' ? $q->where('is_active', false) : $q))
            ->orderBy('classement')
            ->orderBy('id');

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label_fr'     => 'required|string|max:255',
            'label_ar'     => 'required|string|max:255',
            'label_en'     => 'nullable|string|max:255',
            'abbreviation' => 'nullable|string|max:20',
            'classement'   => 'nullable|integer',
            'is_default'   => 'boolean',
            'is_active'    => 'boolean',
            'bg_color'     => 'nullable|string|max:20',
            'text_color'   => 'nullable|string|max:20',
        ]);

        $departement = Departement::create($data);

        return response()->json($departement, 201);
    }

    public function show(Departement $departement): JsonResponse
    {
        return response()->json($departement);
    }

    public function update(Request $request, Departement $departement): JsonResponse
    {
        $data = $request->validate([
            'label_fr'     => 'required|string|max:255',
            'label_ar'     => 'required|string|max:255',
            'label_en'     => 'nullable|string|max:255',
            'abbreviation' => 'nullable|string|max:20',
            'classement'   => 'nullable|integer',
            'is_default'   => 'boolean',
            'is_active'    => 'boolean',
            'bg_color'     => 'nullable|string|max:20',
            'text_color'   => 'nullable|string|max:20',
        ]);

        $departement->update($data);

        return response()->json($departement);
    }

    public function destroy(Departement $departement): JsonResponse
    {
        $departement->delete();

        return response()->json(['message' => 'Département supprimé.']);
    }

    public function toggleActive(Departement $departement): JsonResponse
    {
        $departement->update(['is_active' => !$departement->is_active]);

        return response()->json($departement);
    }

    public function duplicate(Departement $departement): JsonResponse
    {
        $copy = $departement->replicate();
        $copy->label_fr = $departement->label_fr . ' (copie)';
        $copy->abbreviation = null;
        $copy->classement = Departement::max('classement') + 1;
        $copy->save();

        return response()->json($copy, 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items'   => 'required|array',
            'items.*.id' => 'required|integer|exists:departements,id',
            'items.*.classement' => 'required|integer',
        ]);

        AuditLog::auditReorder(Departement::class, $request->items, 'classement');

        return response()->json(['message' => 'Ordre mis à jour.']);
    }

    public function exportPdf(Request $request, DepartementExportService $exportService): Response
    {
        $language = $request->query('lang', 'fr');
        $departements = Departement::orderBy('classement')->get();

        $titles = [
            'ar' => 'قائمة الأقسام',
            'en' => 'Departments List',
            'fr' => 'Liste des Départements',
        ];
        $title = $titles[$language] ?? $titles['fr'];

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "departements_{$timestamp}.pdf";

        $count = $departements->count();
        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'PDF', 'source' => 'Départements', 'count' => $count],
            'success',
            'Export PDF — Départements (' . $count . ' enregistrements)',
            null,
            'Départements — PDF (' . $count . ')'
        );

        $pdf = $exportService->generatePdf($departements, $language, $title, $filename);

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $departements = Departement::orderBy('classement')->get();
        $count = $departements->count();

        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'Excel', 'source' => 'Départements', 'count' => $count],
            'success',
            'Export Excel — Départements (' . $count . ' enregistrements)',
            null,
            'Départements — Excel (' . $count . ')'
        );

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Départements');

        $headers = ['#', 'Label FR', 'Label AR', 'Label EN', 'Abréviation', 'Classement', 'Couleur fond', 'Couleur texte', 'Défaut', 'Actif'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = $sheet->getStyle('A1:J1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $headerStyle->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($departements as $item) {
            $sheet->fromArray([
                $item->id,
                $item->label_fr,
                $item->label_ar,
                $item->label_en ?? '',
                $item->abbreviation ?? '',
                $item->classement,
                $item->bg_color ?? '',
                $item->text_color ?? '',
                $item->is_default ? 'Oui' : 'Non',
                $item->is_active !== false ? 'Oui' : 'Non',
            ], null, 'A' . $row);
            $row++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'departements_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

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
        $departements = Departement::orderBy('classement')->get();
        $count = $departements->count();

        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'CSV', 'source' => 'Départements', 'count' => $count],
            'success',
            'Export CSV — Départements (' . $count . ' enregistrements)',
            null,
            'Départements — CSV (' . $count . ')'
        );

        $filename = 'departements_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($departements) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['#', 'Label FR', 'Label AR', 'Label EN', 'Abréviation', 'Classement', 'Couleur fond', 'Couleur texte', 'Défaut', 'Actif']);

            foreach ($departements as $dept) {
                fputcsv($handle, [
                    $dept->id,
                    $dept->label_fr,
                    $dept->label_ar,
                    $dept->label_en ?? '',
                    $dept->abbreviation ?? '',
                    $dept->classement,
                    $dept->bg_color ?? '',
                    $dept->text_color ?? '',
                    $dept->is_default ? 'Oui' : 'Non',
                    $dept->is_active !== false ? 'Oui' : 'Non',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}
