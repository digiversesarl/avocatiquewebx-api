<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Groupe;
use App\Services\GroupeExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GroupeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Groupe::query()
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

        $groupe = Groupe::create($data);

        return response()->json($groupe, 201);
    }

    public function show(Groupe $groupe): JsonResponse
    {
        return response()->json($groupe);
    }

    public function update(Request $request, Groupe $groupe): JsonResponse
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

        $groupe->update($data);

        return response()->json($groupe);
    }

    public function destroy(Groupe $groupe): JsonResponse
    {
        $groupe->delete();

        return response()->json(['message' => 'Groupe supprimé.']);
    }

    public function toggleActive(Groupe $groupe): JsonResponse
    {
        $groupe->update(['is_active' => !$groupe->is_active]);

        return response()->json($groupe);
    }

    public function duplicate(Groupe $groupe): JsonResponse
    {
        $copy = $groupe->replicate();
        $copy->label_fr = $groupe->label_fr . ' (copie)';
        $copy->classement = Groupe::max('classement') + 1;
        $copy->save();

        return response()->json($copy, 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items'   => 'required|array',
            'items.*.id' => 'required|integer|exists:groupes,id',
            'items.*.classement' => 'required|integer',
        ]);

        AuditLog::auditReorder(Groupe::class, $request->items, 'classement');

        return response()->json(['message' => 'Ordre mis à jour.']);
    }

    public function exportPdf(Request $request, GroupeExportService $exportService): Response
    {
        $language = $request->query('lang', 'fr');
        $groupes = Groupe::orderBy('classement')->get();

        $titles = [
            'ar' => 'قائمة المجموعات',
            'en' => 'Groups List',
            'fr' => 'Liste des Groupes',
        ];
        $title = $titles[$language] ?? $titles['fr'];

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "groupes_{$timestamp}.pdf";

        $count = $groupes->count();
        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'PDF', 'source' => 'Groupes', 'count' => $count],
            'success',
            'Export PDF — Groupes (' . $count . ' enregistrements)',
            null,
            'Groupes — PDF (' . $count . ')'
        );

        $pdf = $exportService->generatePdf($groupes, $language, $title, $filename);

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $groupes = Groupe::orderBy('classement')->get();
        $count = $groupes->count();

        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'Excel', 'source' => 'Groupes', 'count' => $count],
            'success',
            'Export Excel — Groupes (' . $count . ' enregistrements)',
            null,
            'Groupes — Excel (' . $count . ')'
        );

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Groupes');

        $headers = ['#', 'Label FR', 'Label AR', 'Label EN', 'Classement', 'Couleur fond', 'Couleur texte', 'Défaut', 'Actif'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = $sheet->getStyle('A1:I1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $headerStyle->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($groupes as $item) {
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
        $filename = 'groupes_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

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
        $groupes = Groupe::orderBy('classement')->get();
        $count = $groupes->count();

        AuditLog::log(
            'export_data',
            'referentiel',
            null,
            null,
            ['format' => 'CSV', 'source' => 'Groupes', 'count' => $count],
            'success',
            'Export CSV — Groupes (' . $count . ' enregistrements)',
            null,
            'Groupes — CSV (' . $count . ')'
        );

        $filename = 'groupes_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($groupes) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['#', 'Label FR', 'Label AR', 'Label EN', 'Classement', 'Couleur fond', 'Couleur texte', 'Défaut', 'Actif']);

            foreach ($groupes as $groupe) {
                fputcsv($handle, [
                    $groupe->id,
                    $groupe->label_fr,
                    $groupe->label_ar,
                    $groupe->label_en ?? '',
                    $groupe->classement,
                    $groupe->bg_color ?? '',
                    $groupe->text_color ?? '',
                    $groupe->is_default ? 'Oui' : 'Non',
                    $groupe->is_active !== false ? 'Oui' : 'Non',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}
