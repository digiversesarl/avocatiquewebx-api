<?php

namespace App\Http\Controllers;

use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    /**
     * GET /api/translations
     *
     * Liste toutes les traductions
     */
    public function index(): JsonResponse
    {
        $translations = Translation::orderBy('code')->get();

        return response()->json($translations);
    }

    /**
     * POST /api/translations
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'code'        => 'required|string|unique:translations,code',
            'libelle_fr'  => 'required|string',
            'libelle_ar'  => 'required|string',
            'libelle_en'  => 'required|string',
        ]);

        $translation = Translation::create($request->all());

        return response()->json($translation, 201);
    }

    /**
     * GET /api/translations/{translation}
     */
    public function show(Translation $translation): JsonResponse
    {
        return response()->json($translation);
    }

    /**
     * PUT /api/translations/{translation}
     */
    public function update(Request $request, Translation $translation): JsonResponse
    {
        $request->validate([
            'code'        => 'required|string|unique:translations,code,' . $translation->id,
            'libelle_fr'  => 'required|string',
            'libelle_ar'  => 'required|string',
            'libelle_en'  => 'required|string',
        ]);

        $translation->update($request->all());

        return response()->json($translation);
    }

    /**
     * DELETE /api/translations/{translation}
     */
    public function destroy(Translation $translation): JsonResponse
    {
        $translation->delete();

        return response()->json(['message' => 'Traduction supprimée.']);
    }
}
