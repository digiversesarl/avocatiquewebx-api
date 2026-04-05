<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie que l'utilisateur possède au moins une des permissions indiquées.
 *
 * Usage dans les routes :
 *   ->middleware('permission:admin.users')
 *   ->middleware('permission:admin.users,admin.roles')  // logique OR
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json(
            ['message' => 'Accès refusé — permissions insuffisantes.'],
            403
        );
    }
}
