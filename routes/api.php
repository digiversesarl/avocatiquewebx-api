<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DepartementController;
use App\Http\Controllers\FonctionController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\GroupeController;
use App\Http\Controllers\PaysController;
use App\Http\Controllers\VilleController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\Api\CabinetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — AvocatiqueWebX
| Laravel 13 — PHP 8.3
|--------------------------------------------------------------------------
|
| Toutes les routes sont préfixées par /api (configuré dans bootstrap/app.php).
| Authentification : Laravel Sanctum (Bearer token).
|
*/

// ── Routes publiques ──────────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('login',           [AuthController::class, 'login'])->name('login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
});

// ── Translations (publique pour charger dans l'app)
Route::get('translations', [TranslationController::class, 'index'])->name('translations.index');

// ── Routes protégées (Sanctum) ────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function (): void {

    // Auth
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me',      [AuthController::class, 'me'])->name('me');
    });

    // Menu — arbre filtré par les rôles de l'utilisateur connecté (tous les users auth)
    Route::get('menu/tree', [MenuItemController::class, 'tree'])->name('menu.tree');

    // ── Administration : Référentiels ─────────────────────────────────
    Route::post('fonctions/reorder', [FonctionController::class, 'reorder']);
    Route::apiResource('fonctions', FonctionController::class);
    Route::patch('fonctions/{fonction}/toggle-active', [FonctionController::class, 'toggleActive']);
    Route::post('fonctions/{fonction}/duplicate', [FonctionController::class, 'duplicate']);

    Route::post('grades/reorder', [GradeController::class, 'reorder']);
    Route::apiResource('grades', GradeController::class);
    Route::patch('grades/{grade}/toggle-active', [GradeController::class, 'toggleActive']);
    Route::post('grades/{grade}/duplicate', [GradeController::class, 'duplicate']);

    Route::post('departements/reorder', [DepartementController::class, 'reorder']);
    Route::apiResource('departements', DepartementController::class);
    Route::patch('departements/{departement}/toggle-active', [DepartementController::class, 'toggleActive']);
    Route::post('departements/{departement}/duplicate', [DepartementController::class, 'duplicate']);

    Route::post('groupes/reorder', [GroupeController::class, 'reorder']);
    Route::apiResource('groupes', GroupeController::class);
    Route::patch('groupes/{groupe}/toggle-active', [GroupeController::class, 'toggleActive']);
    Route::post('groupes/{groupe}/duplicate', [GroupeController::class, 'duplicate']);

    Route::post('pays/reorder', [PaysController::class, 'reorder']);
    Route::get('pays/export/pdf', [PaysController::class, 'exportPdf']);
    Route::apiResource('pays', PaysController::class)->parameters(['pays' => 'pays']);
    Route::patch('pays/{pays}/toggle-active', [PaysController::class, 'toggleActive']);
    Route::post('pays/{pays}/duplicate', [PaysController::class, 'duplicate']);

    Route::post('villes/reorder', [VilleController::class, 'reorder']);
    Route::get('villes/export/pdf', [VilleController::class, 'exportPdf']);
    Route::apiResource('villes', VilleController::class);
    Route::patch('villes/{ville}/toggle-active', [VilleController::class, 'toggleActive']);
    Route::post('villes/{ville}/duplicate', [VilleController::class, 'duplicate']);

    // ── Administration : Utilisateurs ─────────────────────────────────
    Route::middleware('permission:admin.users')
        ->prefix('users')
        ->name('users.')
        ->group(function (): void {
            Route::post('reorder', [UserController::class, 'reorder'])->name('reorder');
            Route::get('/',        [UserController::class, 'index'])->name('index');
            Route::post('/',       [UserController::class, 'store'])->name('store');
            Route::get('/{user}',  [UserController::class, 'show'])->name('show');
            Route::put('/{user}',  [UserController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
            Route::patch('/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/{user}/photo', [UserController::class, 'uploadPhoto'])->name('photo');
            Route::post('/{user}/attachments', [UserController::class, 'uploadAttachments'])->name('attachments');
            Route::delete('/{user}/attachments/{attachment}', [UserController::class, 'deleteAttachment'])->name('attachments.delete');
            Route::put('/{user}/password', [UserController::class, 'updatePassword'])->name('password');
        });

    // ── Administration : Rôles & Permissions ──────────────────────────
    Route::middleware('permission:admin.roles')
        ->group(function (): void {
            Route::prefix('roles')->name('roles.')->group(function (): void {
                Route::get('/',        [RoleController::class, 'index'])->name('index');
                Route::post('/',       [RoleController::class, 'store'])->name('store');
                Route::get('/{role}',  [RoleController::class, 'show'])->name('show');
                Route::put('/{role}',  [RoleController::class, 'update'])->name('update');
                Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
            });

            // Liste de toutes les permissions disponibles
            Route::get('permissions', [RoleController::class, 'allPermissions'])->name('permissions.index');
        });

    // ── Administration : Gestion du menu ──────────────────────────────
    Route::middleware('permission:admin.settings')
        ->prefix('menu')
        ->name('menu.')
        ->group(function (): void {
            Route::get('/',                          [MenuItemController::class, 'index'])->name('index');
            Route::post('/',                         [MenuItemController::class, 'store'])->name('store');
            Route::post('/reorder',                  [MenuItemController::class, 'reorder'])->name('reorder');
            Route::get('/{menuItem}',                [MenuItemController::class, 'show'])->name('show');
            Route::put('/{menuItem}',                [MenuItemController::class, 'update'])->name('update');
            Route::delete('/{menuItem}',             [MenuItemController::class, 'destroy'])->name('destroy');
            Route::patch('/{menuItem}/toggle-visibility', [MenuItemController::class, 'toggleVisibility'])->name('toggle-visibility');
        });

    // ── Administration : Traductions ──────────────────────────────────
    Route::middleware('permission:admin.settings')
        ->prefix('translations-crud')
        ->name('translations.')
        ->group(function (): void {
            Route::post('/',                    [TranslationController::class, 'store'])->name('store');
            Route::get('/{translation}',        [TranslationController::class, 'show'])->name('show');
            Route::put('/{translation}',        [TranslationController::class, 'update'])->name('update');
            Route::delete('/{translation}',     [TranslationController::class, 'destroy'])->name('destroy');
        });

    // ── Configuration du Cabinet ──────────────────────────────────
    Route::prefix('cabinet')
        ->name('cabinet.')
        ->group(function (): void {
            Route::get('config', [CabinetController::class, 'getConfig'])->name('config.show');
            Route::post('config', [CabinetController::class, 'updateConfig'])->name('config.update');
            Route::post('upload-image', [CabinetController::class, 'uploadImage'])->name('upload-image');
            Route::delete('delete-image', [CabinetController::class, 'deleteImage'])->name('delete-image');

            // Color themes
            Route::get('themes', [CabinetController::class, 'getThemes'])->name('themes.index');
            Route::post('themes', [CabinetController::class, 'storeTheme'])->name('themes.store');
            Route::put('themes/{colorTheme}', [CabinetController::class, 'updateTheme'])->name('themes.update');
            Route::delete('themes/{colorTheme}', [CabinetController::class, 'destroyTheme'])->name('themes.destroy');
        });
});
