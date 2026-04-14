<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Groupe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
