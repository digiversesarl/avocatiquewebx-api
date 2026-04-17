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
use App\Http\Controllers\Api\AuditController;
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
    Route::post('login',           [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:forgot-password')->name('forgot-password');
});

// ── Translations (publique : chargement i18n sans pagination)
Route::get('translations', [TranslationController::class, 'index'])->name('translations.index');

// ── Routes protégées (Sanctum) ────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {

    // Auth
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me',      [AuthController::class, 'me'])->name('me');
    });

    // Menu — arbre filtré par les rôles de l'utilisateur connecté (tous les users auth)
    Route::get('menu/tree', [MenuItemController::class, 'tree'])->name('menu.tree');

    // ── Translations paginées (protégée, pour la page admin) ──────────
    Route::get('translations/paginated', [TranslationController::class, 'paginated'])->name('translations.paginated');

    // ── Administration : Référentiels ─────────────────────────────────
    // Fonctions
    Route::middleware('permission:ref.fonctions.view')->group(function (): void {
        Route::get('fonctions', [FonctionController::class, 'index']);
        Route::get('fonctions/{fonction}', [FonctionController::class, 'show']);
    });
    Route::middleware('permission:ref.fonctions.export')->group(function (): void {
        Route::get('fonctions/export/pdf', [FonctionController::class, 'exportPdf']);
        Route::get('fonctions/export/csv', [FonctionController::class, 'exportCsv']);
        Route::get('fonctions/export/excel', [FonctionController::class, 'exportExcel']);
    });
    Route::middleware('permission:ref.fonctions.create')->group(function (): void {
        Route::post('fonctions', [FonctionController::class, 'store']);
        Route::post('fonctions/{fonction}/duplicate', [FonctionController::class, 'duplicate']);
    });
    Route::middleware('permission:ref.fonctions.edit')->group(function (): void {
        Route::put('fonctions/{fonction}', [FonctionController::class, 'update']);
        Route::patch('fonctions/{fonction}/toggle-active', [FonctionController::class, 'toggleActive']);
        Route::post('fonctions/reorder', [FonctionController::class, 'reorder']);
    });
    Route::middleware('permission:ref.fonctions.delete')->delete('fonctions/{fonction}', [FonctionController::class, 'destroy']);

    // Grades
    Route::middleware('permission:ref.grades.view')->group(function (): void {
        Route::get('grades', [GradeController::class, 'index']);
        Route::get('grades/{grade}', [GradeController::class, 'show']);
    });
    Route::middleware('permission:ref.grades.export')->group(function (): void {
        Route::get('grades/export/pdf', [GradeController::class, 'exportPdf']);
        Route::get('grades/export/csv', [GradeController::class, 'exportCsv']);
        Route::get('grades/export/excel', [GradeController::class, 'exportExcel']);
    });
    Route::middleware('permission:ref.grades.create')->group(function (): void {
        Route::post('grades', [GradeController::class, 'store']);
        Route::post('grades/{grade}/duplicate', [GradeController::class, 'duplicate']);
    });
    Route::middleware('permission:ref.grades.edit')->group(function (): void {
        Route::put('grades/{grade}', [GradeController::class, 'update']);
        Route::patch('grades/{grade}/toggle-active', [GradeController::class, 'toggleActive']);
        Route::post('grades/reorder', [GradeController::class, 'reorder']);
    });
    Route::middleware('permission:ref.grades.delete')->delete('grades/{grade}', [GradeController::class, 'destroy']);

    // Départements
    Route::middleware('permission:ref.departements.view')->group(function (): void {
        Route::get('departements', [DepartementController::class, 'index']);
        Route::get('departements/{departement}', [DepartementController::class, 'show']);
    });
    Route::middleware('permission:ref.departements.export')->group(function (): void {
        Route::get('departements/export/pdf', [DepartementController::class, 'exportPdf']);
        Route::get('departements/export/csv', [DepartementController::class, 'exportCsv']);
        Route::get('departements/export/excel', [DepartementController::class, 'exportExcel']);
    });
    Route::middleware('permission:ref.departements.create')->group(function (): void {
        Route::post('departements', [DepartementController::class, 'store']);
        Route::post('departements/{departement}/duplicate', [DepartementController::class, 'duplicate']);
    });
    Route::middleware('permission:ref.departements.edit')->group(function (): void {
        Route::put('departements/{departement}', [DepartementController::class, 'update']);
        Route::patch('departements/{departement}/toggle-active', [DepartementController::class, 'toggleActive']);
        Route::post('departements/reorder', [DepartementController::class, 'reorder']);
    });
    Route::middleware('permission:ref.departements.delete')->delete('departements/{departement}', [DepartementController::class, 'destroy']);

    // Groupes
    Route::middleware('permission:ref.groupes.view')->group(function (): void {
        Route::get('groupes', [GroupeController::class, 'index']);
        Route::get('groupes/{groupe}', [GroupeController::class, 'show']);
    });
    Route::middleware('permission:ref.groupes.export')->group(function (): void {
        Route::get('groupes/export/pdf', [GroupeController::class, 'exportPdf']);
        Route::get('groupes/export/csv', [GroupeController::class, 'exportCsv']);
        Route::get('groupes/export/excel', [GroupeController::class, 'exportExcel']);
    });
    Route::middleware('permission:ref.groupes.create')->group(function (): void {
        Route::post('groupes', [GroupeController::class, 'store']);
        Route::post('groupes/{groupe}/duplicate', [GroupeController::class, 'duplicate']);
    });
    Route::middleware('permission:ref.groupes.edit')->group(function (): void {
        Route::put('groupes/{groupe}', [GroupeController::class, 'update']);
        Route::patch('groupes/{groupe}/toggle-active', [GroupeController::class, 'toggleActive']);
        Route::post('groupes/reorder', [GroupeController::class, 'reorder']);
    });
    Route::middleware('permission:ref.groupes.delete')->delete('groupes/{groupe}', [GroupeController::class, 'destroy']);

    // Pays
    Route::middleware('permission:ref.pays.view')->group(function (): void {
        Route::get('pays', [PaysController::class, 'index']);
        Route::get('pays/{pays}', [PaysController::class, 'show']);
    });
    Route::middleware('permission:ref.pays.export')->group(function (): void {
        Route::get('pays/export/pdf', [PaysController::class, 'exportPdf']);
        Route::get('pays/export/csv', [PaysController::class, 'exportCsv']);
        Route::get('pays/export/excel', [PaysController::class, 'exportExcel']);
    });
    Route::middleware('permission:ref.pays.create')->group(function (): void {
        Route::post('pays', [PaysController::class, 'store']);
        Route::post('pays/{pays}/duplicate', [PaysController::class, 'duplicate']);
    });
    Route::middleware('permission:ref.pays.edit')->group(function (): void {
        Route::put('pays/{pays}', [PaysController::class, 'update']);
        Route::patch('pays/{pays}/toggle-active', [PaysController::class, 'toggleActive']);
        Route::post('pays/reorder', [PaysController::class, 'reorder']);
    });
    Route::middleware('permission:ref.pays.delete')->delete('pays/{pays}', [PaysController::class, 'destroy']);

    // Villes
    Route::middleware('permission:ref.villes.view')->group(function (): void {
        Route::get('villes', [VilleController::class, 'index']);
        Route::get('villes/{ville}', [VilleController::class, 'show']);
    });
    Route::middleware('permission:ref.villes.export')->group(function (): void {
        Route::get('villes/export/pdf', [VilleController::class, 'exportPdf']);
        Route::get('villes/export/csv', [VilleController::class, 'exportCsv']);
        Route::get('villes/export/excel', [VilleController::class, 'exportExcel']);
    });
    Route::middleware('permission:ref.villes.create')->group(function (): void {
        Route::post('villes', [VilleController::class, 'store']);
        Route::post('villes/{ville}/duplicate', [VilleController::class, 'duplicate']);
    });
    Route::middleware('permission:ref.villes.edit')->group(function (): void {
        Route::put('villes/{ville}', [VilleController::class, 'update']);
        Route::patch('villes/{ville}/toggle-active', [VilleController::class, 'toggleActive']);
        Route::post('villes/reorder', [VilleController::class, 'reorder']);
    });
    Route::middleware('permission:ref.villes.delete')->delete('villes/{ville}', [VilleController::class, 'destroy']);

    // ── Administration : Utilisateurs ─────────────────────────────────
    Route::middleware('permission:admin.users')
        ->prefix('users')
        ->name('users.')
        ->group(function (): void {
            Route::post('reorder',     [UserController::class, 'reorder'])->name('reorder');
            Route::get('/',            [UserController::class, 'index'])->name('index');
            Route::get('export-excel', [UserController::class, 'exportExcel'])->name('export-excel');
            Route::get('export-pdf',   [UserController::class, 'exportPdf'])->name('export-pdf');
            Route::get('export-csv',   [UserController::class, 'exportCsv'])->name('export-csv');
            Route::post('/',           [UserController::class, 'store'])->name('store');
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
                Route::put('/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('permissions.sync');
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
    Route::middleware('permission:admin.cabinet')
        ->prefix('cabinet')
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

    // ── Audit & Sécurité ──────────────────────────────────────────
    Route::middleware('permission:admin.settings')
        ->prefix('audit-logs')
        ->name('audit.')
        ->group(function (): void {
            Route::get('/',            [AuditController::class, 'index'])->name('index');
            Route::get('/users',       [AuditController::class, 'users'])->name('users');
            Route::get('/stats',       [AuditController::class, 'stats'])->name('stats');
            Route::get('/export/xlsx', [AuditController::class, 'exportXlsx'])->name('export.xlsx');
            Route::get('/export/pdf',  [AuditController::class, 'exportPdf'])->name('export.pdf');
            Route::get('/export/csv',  [AuditController::class, 'exportCsv'])->name('export.csv');
            Route::get('/{auditLog}',  [AuditController::class, 'show'])->name('show');
        });
});
