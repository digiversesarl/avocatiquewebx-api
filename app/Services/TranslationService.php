<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service centralisé de traduction pour les libellés d'export et d'interface.
 *
 * Stratégie de cache :
 *   L1 — tableau en mémoire (durée de vie : requête HTTP)
 *   L2 — Redis / file-cache (TTL configurable, défaut 24h)
 *   L3 — base de données translations
 *   L4 — fallback statique ($fallbacks)
 *
 * Clés de traduction recommandées :
 *   export.user.name        → "Nom complet"
 *   export.user.active      → "Actif"
 *   export.invoice.amount   → "Montant"
 *   referentiel.pays.label  → "Pays"
 */
class TranslationService
{
    /** @var array<string, array<string, string>>  ['fr' => ['key' => 'value'], ...] */
    private array $memory = [];

    /** Durée de cache Redis (secondes). Peut être surchargée via config('app.translation_cache_ttl'). */
    private int $ttl;

    /** Locales supportées */
    private array $locales = ['fr', 'ar', 'en'];

    /**
     * Libellés statiques de secours (fallback ultime si DB et cache sont vides).
     * Couvre les colonnes d'export critiques pour éviter toute régression.
     *
     * @var array<string, array<string, string>>
     */
    private array $fallbacks = [
        'fr' => [
            'export.user.matricule'     => 'Matricule',
            'export.user.full_name_fr'  => 'Nom complet',
            'export.user.full_name_ar'  => 'Nom arabe',
            'export.user.login'         => 'Login',
            'export.user.roles'         => 'Rôles',
            'export.user.departement'   => 'Département',
            'export.user.fonction'      => 'Fonction',
            'export.user.langue'        => 'Langue',
            'export.user.email'         => 'Email',
            'export.user.telephone'     => 'Téléphone',
            'export.user.cin'           => 'CIN',
            'export.user.date_entree'   => "Date d'entrée",
            'export.user.active'        => 'Actif',
            'export.pays.code'          => 'Code',
            'export.pays.label_fr'      => 'Libellé (FR)',
            'export.pays.label_ar'      => 'Libellé (AR)',
            'export.pays.label_en'      => 'Libellé (EN)',
            'export.pays.active'        => 'Actif',
        ],
        'ar' => [
            'export.user.matricule'     => 'الرقم المرجعي',
            'export.user.full_name_fr'  => 'الاسم الكامل',
            'export.user.full_name_ar'  => 'الاسم بالعربية',
            'export.user.login'         => 'اسم المستخدم',
            'export.user.roles'         => 'الأدوار',
            'export.user.departement'   => 'القسم',
            'export.user.fonction'      => 'الوظيفة',
            'export.user.langue'        => 'اللغة',
            'export.user.email'         => 'البريد الإلكتروني',
            'export.user.telephone'     => 'الهاتف',
            'export.user.cin'           => 'رقم البطاقة',
            'export.user.date_entree'   => 'تاريخ الدخول',
            'export.user.active'        => 'نشط',
        ],
        'en' => [
            'export.user.matricule'     => 'Reference',
            'export.user.full_name_fr'  => 'Full name',
            'export.user.full_name_ar'  => 'Arabic name',
            'export.user.login'         => 'Login',
            'export.user.roles'         => 'Roles',
            'export.user.departement'   => 'Department',
            'export.user.fonction'      => 'Function',
            'export.user.langue'        => 'Language',
            'export.user.email'         => 'Email',
            'export.user.telephone'     => 'Phone',
            'export.user.cin'           => 'ID card',
            'export.user.date_entree'   => 'Start date',
            'export.user.active'        => 'Active',
        ],
    ];

    public function __construct()
    {
        $this->ttl = (int) config('app.translation_cache_ttl', 86400); // 24h par défaut
    }

    // ──────────────────────────────────────────────────────────────────────────
    // API publique
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Récupère un libellé traduit.
     *
     * @param  string  $key     ex: "export.user.name"
     * @param  string  $locale  ex: "fr" | "ar" | "en"
     * @param  string  $default Valeur par défaut si rien n'est trouvé
     */
    public function get(string $key, string $locale = 'fr', string $default = ''): string
    {
        $locale = $this->normalizeLocale($locale);

        // L1 — mémoire (cache intra-requête)
        if (isset($this->memory[$locale][$key])) {
            return $this->memory[$locale][$key];
        }

        // L2 — Redis / file-cache : on charge tout le dictionnaire d'un coup
        $dict = $this->loadLocale($locale);

        return $dict[$key] ?? $default ?: ($this->fallbacks[$locale][$key] ?? $key);
    }

    /**
     * Récupère plusieurs libellés d'un coup (optimal pour construire les headers d'export).
     *
     * @param  string[]  $keys
     * @return array<string, string>  ['key' => 'libellé traduit']
     */
    public function many(array $keys, string $locale = 'fr'): array
    {
        $locale = $this->normalizeLocale($locale);
        $dict   = $this->loadLocale($locale);
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $dict[$key]
                ?? $this->fallbacks[$locale][$key]
                ?? $key; // dernier recours : retourner la clé elle-même
        }

        return $result;
    }

    /**
     * Retourne tous les libellés d'un préfixe donné.
     * Ex: prefix="export.user" → toutes les clés export.user.*
     *
     * @return array<string, string>
     */
    public function prefix(string $prefix, string $locale = 'fr'): array
    {
        $locale = $this->normalizeLocale($locale);
        $dict   = $this->loadLocale($locale);

        return array_filter(
            $dict,
            fn($key) => str_starts_with($key, $prefix . '.'),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Invalide le cache pour une locale (ou toutes les locales).
     * À appeler après toute modification de la table translations.
     */
    public function invalidate(?string $locale = null): void
    {
        if ($locale) {
            Cache::forget($this->cacheKey($locale));
            unset($this->memory[$locale]);
        } else {
            foreach ($this->locales as $loc) {
                Cache::forget($this->cacheKey($loc));
            }
            $this->memory = [];
        }

        Log::info('TranslationService: cache invalidé', ['locale' => $locale ?? 'all']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Internals
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Charge et met en cache le dictionnaire complet d'une locale.
     * Ordre : L1 mémoire → L2 Redis → L3 base de données.
     *
     * @return array<string, string>
     */
    private function loadLocale(string $locale): array
    {
        // L1 — déjà en mémoire
        if (isset($this->memory[$locale])) {
            return $this->memory[$locale];
        }

        // L2 → L3 — Redis avec fallback DB
        $dict = Cache::remember(
            $this->cacheKey($locale),
            $this->ttl,
            function () use ($locale): array {
                return $this->loadFromDatabase($locale);
            }
        );

        // Stocker en L1 pour la durée de la requête
        $this->memory[$locale] = $dict;

        return $dict;
    }

    /**
     * Charge les traductions depuis la base de données.
     * Retourne un tableau plat [code => valeur].
     *
     * @return array<string, string>
     */
    private function loadFromDatabase(string $locale): array
    {
        try {
            $column = match ($locale) {
                'ar'    => 'libelle_ar',
                'en'    => 'libelle_en',
                default => 'libelle_fr',
            };

            // Requête optimisée : sélectionne uniquement les colonnes nécessaires
            $rows = DB::table('translations')
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->select(['code', $column . ' as value'])
                ->get();

            $dict = [];
            foreach ($rows as $row) {
                $dict[$row->code] = $row->value;
            }

            return $dict;

        } catch (\Throwable $e) {
            Log::warning('TranslationService: impossible de charger la DB', [
                'locale' => $locale,
                'error'  => $e->getMessage(),
            ]);

            // Retourner les fallbacks statiques en cas d'erreur DB
            return $this->fallbacks[$locale] ?? [];
        }
    }

    private function cacheKey(string $locale): string
    {
        return "translations:dict:{$locale}";
    }

    private function normalizeLocale(string $locale): string
    {
        return in_array($locale, $this->locales, true) ? $locale : 'fr';
    }
}
