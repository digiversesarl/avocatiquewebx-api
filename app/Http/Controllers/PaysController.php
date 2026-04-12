<?php

namespace App\Http\Controllers;

use App\Models\Pays;
use App\Services\PaysExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

        foreach ($request->items as $item) {
            Pays::where('id', $item['id'])->update(['classement' => $item['classement']]);
        }

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
}
