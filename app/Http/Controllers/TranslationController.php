<?php

namespace App\Http\Controllers;

use App\Models\Translation;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function __construct(private readonly TranslationService $translations) {}
    /**
     * GET /api/translations
     *
     * Liste paginée des traductions avec recherche et tri.
     */
    public function index(): JsonResponse
    {
        return response()->json(Translation::orderBy('code')->get());
    }

    /**
     * GET /api/translations/paginated
     *
     * Liste paginée (protégée Sanctum) pour la page d'administration.
     */
    public function paginated(Request $request): JsonResponse
    {
        $query = Translation::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . $request->search . '%';
                $q->where(function ($q) use ($s) {
                    $q->where('code', 'like', $s)
                      ->orWhere('libelle_fr', 'like', $s)
                      ->orWhere('libelle_ar', 'like', $s)
                      ->orWhere('libelle_en', 'like', $s);
                });
            });

        $allowedSorts = ['code', 'libelle_fr', 'libelle_ar', 'libelle_en'];
        $sortBy  = in_array($request->input('sort_by'), $allowedSorts) ? $request->input('sort_by') : 'code';
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = min((int) $request->input('per_page', 20), 200);

        return response()->json($query->paginate($perPage));
    }

    /**
     * POST /api/translations
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'        => 'required|string|unique:translations,code',
            'libelle_fr'  => 'required|string',
            'libelle_ar'  => 'required|string',
            'libelle_en'  => 'required|string',
        ]);

        $translation = Translation::create($data);

        $this->translations->invalidate(); // invalide le cache pour toutes les locales

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
        $data = $request->validate([
            'code'        => 'required|string|unique:translations,code,' . $translation->id,
            'libelle_fr'  => 'required|string',
            'libelle_ar'  => 'required|string',
            'libelle_en'  => 'required|string',
        ]);

        $translation->update($data);

        $this->translations->invalidate(); // invalide le cache pour toutes les locales

        return response()->json($translation);
    }

    /**
     * DELETE /api/translations/{translation}
     */
    public function destroy(Translation $translation): JsonResponse
    {
        $translation->delete();

        $this->translations->invalidate(); // invalide le cache pour toutes les locales

        return response()->json(['message' => 'Traduction supprimée.']);
    }
}
