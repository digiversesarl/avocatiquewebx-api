<?php

namespace App\Http\Controllers;

use App\Models\Departement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        foreach ($request->items as $item) {
            Departement::where('id', $item['id'])->update(['classement' => $item['classement']]);
        }

        return response()->json(['message' => 'Ordre mis à jour.']);
    }
}
