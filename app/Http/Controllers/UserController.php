<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OTPHP\TOTP;

class UserController extends Controller
{
    /**
     * GET /api/users
     *
     * Liste paginée avec filtres : search, status, role, fonction, departement, groupe.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::with(['roles:id,name'])
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where(function ($q) use ($request): void {
                    $q->where('full_name_fr', 'like', "%{$request->search}%")
                      ->orWhere('full_name_ar', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%")
                      ->orWhere('matricule', 'like', "%{$request->search}%")
                      ->orWhere('cin', 'like', "%{$request->search}%")
                      ->orWhere('login', 'like', "%{$request->search}%");
                })
            )
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('active', $request->status === 'active')
            )
            ->when(
                $request->filled('role'),
                fn ($q) => $q->whereHas(
                    'roles',
                    fn ($rq) => $rq->where('name', $request->role)
                )
            )
            ->when(
                $request->filled('fonction'),
                fn ($q) => $q->where('fonction', $request->fonction)
            )
            ->when(
                $request->filled('departement'),
                fn ($q) => $q->where('departement', $request->departement)
            )
            ->when(
                $request->filled('groupe'),
                fn ($q) => $q->whereJsonContains('groupes', $request->groupe)
            )
            ->orderBy('classement')
            ->orderBy('full_name_fr')
            ->paginate((int) ($request->per_page ?? 20));

        return response()->json($users);
    }

    /**
     * POST /api/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['roles', 'photo', 'attachments']);

        // Photo upload
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('personnel/photos', 'public');
        }

        $user = User::create($data);

        // Sync roles
        if ($request->filled('roles')) {
            $user->roles()->sync(
                Role::whereIn('name', $request->roles)->pluck('id')
            );
        }

        // Attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $user->attachments()->create([
                    'name'      => $file->getClientOriginalName(),
                    'type'      => $file->getClientMimeType(),
                    'file_path' => $file->store('personnel/attachments', 'public'),
                ]);
            }
        }

        return response()->json(
            $user->load(['roles:id,name', 'attachments']),
            201
        );
    }

    /**
     * GET /api/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        return response()->json(
            $user->load(['roles:id,name', 'attachments'])
        );
    }

    /**
     * PUT /api/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->safe()->except(['roles', 'photo', 'attachments']);

        // Password : only update if provided
        if (! $request->filled('password')) {
            unset($data['password']);
        }

        // Photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            $data['photo'] = $request->file('photo')->store('personnel/photos', 'public');
        }

        $user->update($data);

        // Sync roles
        if ($request->has('roles')) {
            $user->roles()->sync(
                Role::whereIn('name', $request->roles)->pluck('id')
            );
        }

        // New attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $user->attachments()->create([
                    'name'      => $file->getClientOriginalName(),
                    'type'      => $file->getClientMimeType(),
                    'file_path' => $file->store('personnel/attachments', 'public'),
                ]);
            }
        }

        return response()->json(
            $user->load(['roles:id,name', 'attachments'])
        );
    }

    /**
     * DELETE /api/users/{user}
     *
     * Soft-delete. Impossible de se supprimer soi-même.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(
                ['message' => 'Impossible de supprimer votre propre compte.'],
                422
            );
        }

        // Clean photo
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    /**
     * PATCH /api/users/{user}/toggle-active
     */
    public function toggleActive(User $user): JsonResponse
    {
        $user->update(['active' => ! $user->active]);

        return response()->json($user);
    }

    /**
     * PATCH /api/users/{user}/toggle-tfa
     */
    public function toggleTfa(User $user): JsonResponse
    {
        if ($user->tfa_enabled) {
            $user->update(['tfa_enabled' => false, 'tfa_secret' => null]);
        } else {
            $totp = TOTP::create();
            $user->update(['tfa_enabled' => true, 'tfa_secret' => $totp->getSecret()]);
        }

        return response()->json($user->only(['id', 'tfa_enabled']));
    }

    /**
     * DELETE /api/users/{user}/attachments/{attachment}
     */
    public function deleteAttachment(User $user, int $attachmentId): JsonResponse
    {
        $attachment = $user->attachments()->findOrFail($attachmentId);
        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['message' => 'Pièce jointe supprimée.']);
    }
}
