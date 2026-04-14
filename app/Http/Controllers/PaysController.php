<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Pays;
use App\Services\PaysExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PaysController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Pays::select('id', 'code', 'label_fr', 'label_ar', 'label_en', 'classement', 'is_active', 'is_default', 'bg_color', 'text_color', 'created_at', 'updated_at')
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
            'code'       => 'required|string|max:4|unique:pays,code',
            'label_fr'   => 'required|string|max:255',
            'label_ar'   => 'required|string|max:255',
            'label_en'   => 'required|string|max:255',
            'classement' => 'nullable|integer',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'bg_color'   => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
        ]);

        $pays = Pays::create($data);

        return response()->json($pays, 201);
    }

    public function show(Pays $pays): JsonResponse
    {
        return response()->json($pays->load('villes'));
    }

    public function update(Request $request, Pays $pays): JsonResponse
    {
        $data = $request->validate([
            'code'       => 'required|string|max:4|unique:pays,code,' . $pays->id,
            'label_fr'   => 'required|string|max:255',
            'label_ar'   => 'required|string|max:255',
            'label_en'   => 'required|string|max:255',
            'classement' => 'nullable|integer',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'bg_color'   => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
        ]);

        $pays->update($data);

        return response()->json($pays);
    }

    public function destroy(Pays $pays): JsonResponse
    {
        $pays->delete();

        return response()->json(['message' => 'Pays supprimé.']);
    }

    public function toggleActive(Pays $pays): JsonResponse
    {
        $pays->update(['is_active' => !$pays->is_active]);

        return response()->json($pays);
    }

    public function duplicate(Pays $pays): JsonResponse
    {
        $copy = $pays->replicate();
        $copy->label_fr = $pays->label_fr . ' (copie)';
        $copy->code = null;
        $copy->classement = Pays::max('classement') + 1;
        $copy->save();

        return response()->json($copy, 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items'   => 'required|array',
            'items.*.id' => 'required|integer|exists:pays,id',
            'items.*.classement' => 'required|integer',
        ]);

        AuditLog::auditReorder(Pays::class, $request->items, 'classement');

        return response()->json(['message' => 'Ordre mis à jour.']);
    }

    public function exportPdf(Request $request, PaysExportService $exportService): Response
    {
        $language = $request->query('lang', 'fr');
        $pays = Pays::orderBy('classement')->get();
        
        // Titre en fonction de la langue
        $titles = [
            'ar' => 'قائمة الدول',
            'en' => 'Countries List',
            'fr' => 'Liste des Pays',
        ];
        $title = $titles[$language] ?? $titles['fr'];
        
        // Générer le nom de fichier avec date et heure
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "pays_{$timestamp}.pdf";
        
        $pdf = $exportService->generatePdf($pays, $language, $title, $filename);
        
        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $pays = Pays::orderBy('classement')->get();
        $count = $pays->count();

        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'Excel', 'source' => 'Pays', 'count' => $count],
            'success',
            'Export Excel — Pays (' . $count . ' enregistrements)',
            null,
            'Pays — Excel (' . $count . ')'
        );

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pays');

        $headers = ['#', 'Code', 'Label FR', 'Label AR', 'Label EN', 'Classement', 'Couleur fond', 'Couleur texte', 'Défaut', 'Actif'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = $sheet->getStyle('A1:J1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $headerStyle->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($pays as $item) {
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
        $filename = 'pays_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

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
        $pays = Pays::orderBy('classement')->get();
        $count = $pays->count();

        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'CSV', 'source' => 'Pays', 'count' => $count],
            'success',
            'Export CSV — Pays (' . $count . ' enregistrements)',
            null,
            'Pays — CSV (' . $count . ')'
        );

        $filename = 'pays_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($pays) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['#', 'Code', 'Label FR', 'Label AR', 'Label EN', 'Classement', 'Couleur fond', 'Couleur texte', 'Défaut', 'Actif']);

            foreach ($pays as $country) {
                fputcsv($handle, [
                    $country->id,
                    $country->code ?? '',
                    $country->label_fr,
                    $country->label_ar,
                    $country->label_en ?? '',
                    $country->classement,
                    $country->bg_color ?? '',
                    $country->text_color ?? '',
                    $country->is_default ? 'Oui' : 'Non',
                    $country->is_active !== false ? 'Oui' : 'Non',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}
