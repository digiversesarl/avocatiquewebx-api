<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
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

// ── Routes protégées (Sanctum) ────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function (): void {

    // Auth
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me',      [AuthController::class, 'me'])->name('me');
    });

    // Menu — arbre filtré par les rôles de l'utilisateur connecté (tous les users auth)
    Route::get('menu/tree', [MenuItemController::class, 'tree'])->name('menu.tree');

    // ── Administration : Utilisateurs ─────────────────────────────────
    Route::middleware('permission:admin.users')
        ->prefix('users')
        ->name('users.')
        ->group(function (): void {
            Route::get('/',        [UserController::class, 'index'])->name('index');
            Route::post('/',       [UserController::class, 'store'])->name('store');
            Route::get('/{user}',  [UserController::class, 'show'])->name('show');
            Route::put('/{user}',  [UserController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
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
});
