<?php

namespace App\Http\Controllers;

use App\Models\Fonction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        foreach ($request->items as $item) {
            Fonction::where('id', $item['id'])->update(['classement' => $item['classement']]);
        }

        return response()->json(['message' => 'Ordre mis à jour.']);
    }
}
