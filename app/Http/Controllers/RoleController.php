<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    /**
     * GET /api/roles
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions:id,name,module,action')
            ->orderBy('name')
            ->get();

        return response()->json($roles);
    }

    /**
     * POST /api/roles
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create($request->only(['name', 'display_name', 'description']));

        if ($request->filled('permissions')) {
            AuditLog::auditSync(
                $role,
                'permissions',
                Permission::whereIn('name', $request->permissions)->pluck('id')->toArray(),
                'role_granted',
                'roles'
            );
        }

        return response()->json($role->load('permissions:id,name,module,action'), 201);
    }

    /**
     * GET /api/roles/{role}
     */
    public function show(Role $role): JsonResponse
    {
        return response()->json($role->load('permissions:id,name,module,action'));
    }

    /**
     * PUT /api/roles/{role}
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role->update($request->only(['display_name', 'description']));

        if ($request->has('permissions')) {
            AuditLog::auditSync(
                $role,
                'permissions',
                Permission::whereIn('name', $request->permissions)->pluck('id')->toArray(),
                'role_granted',
                'roles'
            );
        }

        return response()->json($role->load('permissions:id,name,module,action'));
    }

    /**
     * DELETE /api/roles/{role}
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->name === 'admin') {
            return response()->json(
                ['message' => 'Impossible de supprimer le rôle administrateur.'],
                422
            );
        }

        $role->delete();

        return response()->json(['message' => 'Rôle supprimé.']);
    }

    /**
     * GET /api/permissions
     *
     * Retourne toutes les permissions disponibles, groupées par module.
     */
    public function allPermissions(): JsonResponse
    {
        $grouped = Permission::orderBy('module')
            ->orderBy('action')
            ->get()
            ->groupBy('module');

        return response()->json($grouped);
    }
}
