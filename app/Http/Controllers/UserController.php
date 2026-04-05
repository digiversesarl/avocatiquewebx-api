<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * GET /api/users
     *
     * Liste paginée avec filtres optionnels : search, status, role.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles:id,name')
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where(function ($q) use ($request): void {
                    $q->where('name', 'like', '%'.$request->search.'%')
                      ->orWhere('email', 'like', '%'.$request->search.'%');
                })
            )
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->status)
            )
            ->when(
                $request->filled('role'),
                fn ($q) => $q->whereHas(
                    'roles',
                    fn ($rq) => $rq->where('name', $request->role)
                )
            )
            ->orderBy('name')
            ->paginate((int) ($request->per_page ?? 20));

        return response()->json($users);
    }

    /**
     * POST /api/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password,   // hashé via cast
            'status'   => $request->status ?? 'active',
        ]);

        if ($request->filled('roles')) {
            $user->roles()->sync(
                Role::whereIn('name', $request->roles)->pluck('id')
            );
        }

        return response()->json($user->load('roles:id,name'), 201);
    }

    /**
     * GET /api/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('roles:id,name'));
    }

    /**
     * PUT /api/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->only(['name', 'email', 'status']);

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $user->update($data);

        if ($request->has('roles')) {
            $user->roles()->sync(
                Role::whereIn('name', $request->roles)->pluck('id')
            );
        }

        return response()->json($user->load('roles:id,name'));
    }

    /**
     * DELETE /api/users/{user}
     *
     * Suppression douce (soft-delete). Impossible de se supprimer soi-même.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(
                ['message' => 'Impossible de supprimer votre propre compte.'],
                422
            );
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }
}
