<?php

namespace App\Http\Controllers;

use App\Models\Ville;
use App\Services\VillesExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
        $copy->code = null;
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

        foreach ($request->items as $item) {
            Ville::where('id', $item['id'])->update(['classement' => $item['classement']]);
        }

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
}
