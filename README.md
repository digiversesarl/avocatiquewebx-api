# AvocatiqueWebX — API Backend

> API REST Laravel pour la gestion d'un cabinet d'avocats (FR/AR/EN, RTL-aware).

## Stack

- **Laravel 13** · PHP 8.3 · Sanctum (Bearer token) · mPDF
- Base de données : MySQL (dev/prod), SQLite (tests)

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

## Lancement

```bash
composer dev          # serveur + queue + pail + vite (tout en un)
# ou
php artisan serve     # serveur seul (port 8000)
```

## Tests

```bash
# Tous les tests
php artisan test

# Tests de sécurité uniquement (audit)
php artisan test --filter=SecurityAuditTest

# Tests d'intégration uniquement
php artisan test --filter="AuthIntegrationTest|UserIntegrationTest|RolePermissionIntegrationTest|MenuItemIntegrationTest|TranslationIntegrationTest|ReferentielIntegrationTest|CabinetConfigIntegrationTest|AuditLogIntegrationTest"

# Un test spécifique
php artisan test --filter=test_login_rate_limited_after_5_attempts
```

### Tests de sécurité (`SecurityAuditTest`)

24 tests automatisés couvrant les points d'audit suivants :

| # | Catégorie | Tests | Vérifie |
|---|-----------|-------|---------|
| 1-3 | **Security Headers** | 3 | `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy` sur routes publiques, 401 et authentifiées |
| 4-5 | **Rate Limiting — Login** | 2 | Blocage après 5 tentatives/min + headers `X-RateLimit-Limit` et `Retry-After` |
| 6 | **Rate Limiting — Forgot Password** | 1 | Blocage après 3 tentatives/min |
| 7 | **Rate Limiting — API** | 1 | Header `X-RateLimit-Limit: 120` sur requêtes authentifiées |
| 8-9 | **Password — Pas de double hashing** | 2 | Reset mot de passe → login fonctionne, `Hash::check()` valide en base |
| 10-13 | **Password — Validation forte** | 4 | Min 8 caractères, lettres requises, chiffres requis, mot de passe fort accepté |
| 14 | **Sanctum — Expiration tokens** | 1 | `config('sanctum.expiration')` non-null, > 0, ≤ 480 min |
| 15 | **Mass Assignment — tfa_secret** | 1 | `tfa_secret` ne peut pas être injecté via PUT /users |
| 16-17 | **Mass Assignment — Translations** | 2 | Champs supplémentaires ignorés sur store et update |
| 18-19 | **Protection des routes** | 2 | Routes protégées → 401 sans token, route publique → 200 |
| 20-21 | **Anti-énumération** | 2 | Même message d'erreur pour email inexistant et mauvais mot de passe |
| 22 | **Compte désactivé** | 1 | Compte `inactive` → 403 |
| 23 | **RBAC — Permissions** | 1 | Utilisateur sans permission → 403 |
| 24 | **Logout — Révocation token** | 1 | Token supprimé en base après logout |

### Tests d'intégration (115 tests)

Tests end-to-end pour chaque service/contrôleur de l'API :

| Fichier | Tests | Couverture |
|---------|-------|------------|
| `AuthIntegrationTest` | 12 | Login, logout, me, forgot-password, validation, register, compte inactif |
| `UserIntegrationTest` | 16 | CRUD utilisateurs, toggle-active, password, photo, pièces jointes, reorder, RBAC |
| `RolePermissionIntegrationTest` | 12 | CRUD rôles, allPermissions, syncPermissions, contrôle d'accès RBAC |
| `MenuItemIntegrationTest` | 12 | CRUD menu, arbre filtré par rôle, toggle-visibility, reorder, cascade delete |
| `TranslationIntegrationTest` | 10 | Index public, admin paginé, CRUD, unicité code, contrôle d'accès |
| `ReferentielIntegrationTest` | 30 | CRUD + toggle + duplicate + reorder pour Pays, Villes, Fonctions, Grades, Départements, Groupes |
| `CabinetConfigIntegrationTest` | 12 | Config singleton get/update, thèmes couleur CRUD, upload/delete image, règles métier |
| `AuditLogIntegrationTest` | 11 | Index paginé + filtres/tri, détail, liste utilisateurs, stats, piste d'audit, contrôle d'accès |

### Lancer l'audit de sécurité

```bash
php artisan test --filter=SecurityAuditTest -v
```

Résultat attendu : **24 passed (74 assertions)**

## Variables d'environnement clés

| Variable | Description | Défaut |
|----------|-------------|--------|
| `SANCTUM_TOKEN_EXPIRATION` | Durée de vie des tokens (minutes) | `480` |
| `FRONTEND_URL` | URL du frontend (CORS) | — |
| `SANCTUM_STATEFUL_DOMAINS` | Domaines Sanctum stateful | `localhost,...` |

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
