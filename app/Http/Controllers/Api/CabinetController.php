<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CabinetConfig;
use App\Models\ColorTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CabinetController extends Controller
{
    /**
     * Récupérer la configuration du cabinet
     */
    public function getConfig(): JsonResponse
    {
        $config = CabinetConfig::getConfig();
        return response()->json(['data' => $config]);
    }

    /**
     * Mettre à jour la configuration du cabinet
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $config = CabinetConfig::getConfig();
        
        $validated = $request->validate([
            'firm_name_fr' => 'nullable|string|max:255',
            'firm_name_ar' => 'nullable|string|max:255',
            'firm_name_en' => 'nullable|string|max:255',
            'firm_address_fr' => 'nullable|string',
            'firm_address_ar' => 'nullable|string',
            'firm_address_en' => 'nullable|string',
            'city_fr' => 'nullable|string|max:100',
            'city_ar' => 'nullable|string|max:100',
            'city_en' => 'nullable|string|max:100',
            'firm_email' => 'nullable|email',
            'firm_phone' => 'nullable|string|max:20',
            'firm_fax' => 'nullable|string|max:20',
            'firm_site_web' => 'nullable|string|max:255',
            'firm_code' => 'nullable|string|max:50',
            'firm_barreau' => 'nullable|string|max:255',
            'firm_sms_number' => 'nullable|string|max:20',
            'firm_patente' => 'nullable|string|max:100',
            'firm_ice' => 'nullable|string|max:100',
            'firm_cnss' => 'nullable|string|max:100',
            'firm_banque' => 'nullable|string|max:255',
            'firm_compte_bancaire' => 'nullable|string|max:255',
            'firm_agence' => 'nullable|string|max:255',
            'color_theme' => 'nullable|string|exists:color_themes,slug',
        ]);

        $config->update($validated);

        return response()->json(['data' => $config, 'message' => 'Configuration mise à jour avec succès']);
    }

    // ── Color Themes CRUD ─────────────────────────────────────────

    /**
     * Lister tous les thèmes de couleur
     */
    public function getThemes(): JsonResponse
    {
        $themes = ColorTheme::orderBy('classement')->get();
        return response()->json(['data' => $themes]);
    }

    /**
     * Créer un nouveau thème
     */
    public function storeTheme(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label'  => 'required|string|max:100',
            'color1' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color2' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color3' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $slug = Str::slug($validated['label']);
        $base = $slug;
        $i = 1;
        while (ColorTheme::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $maxClassement = ColorTheme::max('classement') ?? 0;

        $theme = ColorTheme::create([
            ...$validated,
            'slug'       => $slug,
            'is_default' => false,
            'classement' => $maxClassement + 1,
        ]);

        return response()->json(['data' => $theme, 'message' => 'Thème créé avec succès'], 201);
    }

    /**
     * Mettre à jour un thème existant
     */
    public function updateTheme(Request $request, ColorTheme $colorTheme): JsonResponse
    {
        $validated = $request->validate([
            'label'  => 'sometimes|string|max:100',
            'color1' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color2' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color3' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $colorTheme->update($validated);

        return response()->json(['data' => $colorTheme, 'message' => 'Thème mis à jour']);
    }

    /**
     * Supprimer un thème (uniquement les thèmes custom, pas les is_default)
     */
    public function destroyTheme(ColorTheme $colorTheme): JsonResponse
    {
        if ($colorTheme->is_default) {
            return response()->json(['message' => 'Impossible de supprimer un thème par défaut'], 422);
        }

        // Si le cabinet utilise ce thème, revenir à 'green'
        $config = CabinetConfig::getConfig();
        if ($config->color_theme === $colorTheme->slug) {
            $config->update(['color_theme' => 'green']);
        }

        $colorTheme->delete();

        return response()->json(['message' => 'Thème supprimé']);
    }

    /**
     * Télécharger une image du cabinet
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:5120', // 5MB
            'type' => 'required|in:logo,header,footer,signature',
        ]);

        $config = CabinetConfig::getConfig();
        $type = $request->input('type');
        $field = "firm_{$type}_url";

        // Supprimer l'ancienne image si elle existe
        if ($config->$field) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $config->$field));
        }

        // Stocker la nouvelle image
        $path = $request->file('file')->store("cabinet/{$type}", 'public');
        $url = '/storage/' . $path;

        $config->update([$field => $url]);

        return response()->json(['data' => ['url' => $url], 'message' => 'Image téléchargée avec succès']);
    }

    /**
     * Supprimer une image du cabinet
     */
    public function deleteImage(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:logo,header,footer,signature',
        ]);

        $config = CabinetConfig::getConfig();
        $type = $request->input('type');
        $field = "firm_{$type}_url";

        // Supprimer le fichier
        if ($config->$field) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $config->$field));
        }

        // Mettre à jour la configuration
        $config->update([$field => null]);

        return response()->json(['message' => 'Image supprimée avec succès']);
    }
}
