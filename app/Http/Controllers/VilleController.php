<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Ville;
use App\Services\VillesExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class VilleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ville::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $s = '%' . $request->search . '%';
                $q->where('label_fr', 'like', $s)
                  ->orWhere('label_ar', 'like', $s)
                  ->orWhere('label_en', 'like', $s)
                  ->orWhere('code', 'like', $s);
            }))
            ->when($request->filled('status'), fn ($q) => $request->status === 'active' 
                ? $q->where('is_active', true)
                : ($request->status === 'inactive' 
                    ? $q->where('is_active', false)
                    : $q))
            ->when($request->filled('pays_id'), fn ($q) => $q->where('pays_id', $request->pays_id))
            ->orderBy('classement')
            ->orderBy('id');

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label_fr'   => 'required|string|max:255',
            'label_ar'   => 'required|string|max:255',
            'label_en'   => 'required|string|max:255',
            'pays_id'    => 'required|integer|exists:pays,id',
            'classement' => 'nullable|integer',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'bg_color'   => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
        ]);

        $ville = Ville::create($data);

        return response()->json($ville->load('pays:id,label_fr,code'), 201);
    }

    public function show(Ville $ville): JsonResponse
    {
        return response()->json($ville->load('pays:id,label_fr,code'));
    }

    public function update(Request $request, Ville $ville): JsonResponse
    {
        $data = $request->validate([
            'label_fr'   => 'required|string|max:255',
            'label_ar'   => 'required|string|max:255',
            'label_en'   => 'required|string|max:255',
            'pays_id'    => 'required|integer|exists:pays,id',
            'classement' => 'nullable|integer',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'bg_color'   => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
        ]);

        $ville->update($data);

        return response()->json($ville->load('pays:id,label_fr,code'));
    }

    public function destroy(Ville $ville): JsonResponse
    {
        $ville->delete();

        return response()->json(['message' => 'Ville supprimée.']);
    }

    public function toggleActive(Ville $ville): JsonResponse
    {
        $ville->update(['is_active' => !$ville->is_active]);

        return response()->json($ville);
    }

    public function duplicate(Ville $ville): JsonResponse
    {
        $copy = $ville->replicate();
        $copy->label_fr = $ville->label_fr . ' (copie)';
        $copy->classement = Ville::max('classement') + 1;
        $copy->save();

        return response()->json($copy->load('pays:id,label_fr,code'), 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items'   => 'required|array',
            'items.*.id' => 'required|integer|exists:villes,id',
            'items.*.classement' => 'required|integer',
        ]);

        AuditLog::auditReorder(Ville::class, $request->items, 'classement');

        return response()->json(['message' => 'Ordre mis à jour.']);
    }

    public function exportPdf(Request $request, VillesExportService $exportService): Response
    {
        $language = $request->query('lang', 'fr');
        $villes = Ville::with('pays')->orderBy('classement')->get();
        
        // Titre en fonction de la langue
        $titles = [
            'ar' => 'قائمة المدن',
            'en' => 'Cities List',
            'fr' => 'Liste des Villes',
        ];
        $title = $titles[$language] ?? $titles['fr'];
        
        // Générer le nom de fichier avec date et heure
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "villes_{$timestamp}.pdf";
        
        $pdf = $exportService->generatePdf($villes, $language, $title, $filename);
        
        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $villes = Ville::with('pays')->orderBy('classement')->get();
        $count = $villes->count();

        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'Excel', 'source' => 'Villes', 'count' => $count],
            'success',
            'Export Excel — Villes (' . $count . ' enregistrements)',
            null,
            'Villes — Excel (' . $count . ')'
        );

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Villes');

        $headers = ['#', 'Label FR', 'Label AR', 'Label EN', 'Pays', 'Abréviation', 'Classement', 'Couleur fond', 'Couleur texte', 'Défaut', 'Actif'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = $sheet->getStyle('A1:K1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $headerStyle->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($villes as $item) {
            $sheet->fromArray([
                $item->id,
                $item->label_fr,
                $item->label_ar,
                $item->label_en ?? '',
                $item->pays->label_fr ?? '—',
                $item->abbreviation ?? '',
                $item->classement,
                $item->bg_color ?? '',
                $item->text_color ?? '',
                $item->is_default ? 'Oui' : 'Non',
                $item->is_active !== false ? 'Oui' : 'Non',
            ], null, 'A' . $row);
            $row++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'villes_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

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
        $villes = Ville::with('pays')->orderBy('classement')->get();
        $count = $villes->count();

        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'CSV', 'source' => 'Villes', 'count' => $count],
            'success',
            'Export CSV — Villes (' . $count . ' enregistrements)',
            null,
            'Villes — CSV (' . $count . ')'
        );

        $filename = 'villes_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($villes) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['#', 'Label FR', 'Label AR', 'Label EN', 'Pays', 'Abréviation', 'Classement', 'Couleur fond', 'Couleur texte', 'Défaut', 'Actif']);

            foreach ($villes as $ville) {
                fputcsv($handle, [
                    $ville->id,
                    $ville->label_fr,
                    $ville->label_ar,
                    $ville->label_en ?? '',
                    $ville->pays->label_fr ?? '—',
                    $ville->abbreviation ?? '',
                    $ville->classement,
                    $ville->bg_color ?? '',
                    $ville->text_color ?? '',
                    $ville->is_default ? 'Oui' : 'Non',
                    $ville->is_active !== false ? 'Oui' : 'Non',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}
