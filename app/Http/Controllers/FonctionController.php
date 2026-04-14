<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Fonction;
use App\Services\FonctionExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FonctionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Fonction::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $s = '%' . $request->search . '%';
                $q->where('label_fr', 'like', $s)
                  ->orWhere('label_ar', 'like', $s)
                  ->orWhere('label_en', 'like', $s)
                  ->orWhere('code', 'like', $s);
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
            'code'       => 'nullable|string|max:20|unique:fonctions,code',
            'label_fr'   => 'required|string|max:255',
            'label_ar'   => 'required|string|max:255',
            'label_en'   => 'nullable|string|max:255',
            'classement' => 'nullable|integer',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'bg_color'   => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
        ]);

        $fonction = Fonction::create($data);

        return response()->json($fonction, 201);
    }

    public function show(Fonction $fonction): JsonResponse
    {
        return response()->json($fonction);
    }

    public function update(Request $request, Fonction $fonction): JsonResponse
    {
        $data = $request->validate([
            'code'       => 'nullable|string|max:20|unique:fonctions,code,' . $fonction->id,
            'label_fr'   => 'required|string|max:255',
            'label_ar'   => 'required|string|max:255',
            'label_en'   => 'nullable|string|max:255',
            'classement' => 'nullable|integer',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'bg_color'   => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
        ]);

        $fonction->update($data);

        return response()->json($fonction);
    }

    public function destroy(Fonction $fonction): JsonResponse
    {
        $fonction->delete();

        return response()->json(['message' => 'Fonction supprimée.']);
    }

    public function toggleActive(Fonction $fonction): JsonResponse
    {
        $fonction->update(['is_active' => !$fonction->is_active]);

        return response()->json($fonction);
    }

    public function duplicate(Fonction $fonction): JsonResponse
    {
        $copy = $fonction->replicate();
        $copy->label_fr = $fonction->label_fr . ' (copie)';
        $copy->code = null;
        $copy->classement = Fonction::max('classement') + 1;
        $copy->save();

        return response()->json($copy, 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items'   => 'required|array',
            'items.*.id' => 'required|integer|exists:fonctions,id',
            'items.*.classement' => 'required|integer',
        ]);

        AuditLog::auditReorder(Fonction::class, $request->items, 'classement');

        return response()->json(['message' => 'Ordre mis à jour.']);
    }

    public function exportPdf(Request $request, FonctionExportService $exportService): Response
    {
        $language = $request->query('lang', 'fr');
        $fonctions = Fonction::orderBy('classement')->get();

        $titles = [
            'ar' => 'قائمة الوظائف',
            'en' => 'Functions List',
            'fr' => 'Liste des Fonctions',
        ];
        $title = $titles[$language] ?? $titles['fr'];

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "fonctions_{$timestamp}.pdf";

        $count = $fonctions->count();
        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'PDF', 'source' => 'Fonctions', 'count' => $count],
            'success',
            'Export PDF — Fonctions (' . $count . ' enregistrements)',
            null,
            'Fonctions — PDF (' . $count . ')'
        );

        $pdf = $exportService->generatePdf($fonctions, $language, $title, $filename);

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $fonctions = Fonction::orderBy('classement')->get();
        $count = $fonctions->count();

        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'Excel', 'source' => 'Fonctions', 'count' => $count],
            'success',
            'Export Excel — Fonctions (' . $count . ' enregistrements)',
            null,
            'Fonctions — Excel (' . $count . ')'
        );

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Fonctions');

        $headers = ['#', 'Code', 'Label FR', 'Label AR', 'Label EN', 'Classement', 'Couleur fond', 'Couleur texte', 'Défaut', 'Actif'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = $sheet->getStyle('A1:J1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $headerStyle->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($fonctions as $item) {
            $sheet->fromArray([
                $item->id,
                $item->code ?? '',
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

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'fonctions_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

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
        $fonctions = Fonction::orderBy('classement')->get();
        $count = $fonctions->count();

        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'CSV', 'source' => 'Fonctions', 'count' => $count],
            'success',
            'Export CSV — Fonctions (' . $count . ' enregistrements)',
            null,
            'Fonctions — CSV (' . $count . ')'
        );

        $filename = 'fonctions_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($fonctions) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['#', 'Code', 'Label FR', 'Label AR', 'Label EN', 'Classement', 'Couleur fond', 'Couleur texte', 'Défaut', 'Actif']);

            foreach ($fonctions as $fonction) {
                fputcsv($handle, [
                    $fonction->id,
                    $fonction->code ?? '',
                    $fonction->label_fr,
                    $fonction->label_ar,
                    $fonction->label_en ?? '',
                    $fonction->classement,
                    $fonction->bg_color ?? '',
                    $fonction->text_color ?? '',
                    $fonction->is_default ? 'Oui' : 'Non',
                    $fonction->is_active !== false ? 'Oui' : 'Non',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}
