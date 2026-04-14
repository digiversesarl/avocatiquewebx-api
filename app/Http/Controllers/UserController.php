<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Departement;
use App\Models\Groupe;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $users = User::with(['roles:id,name', 'groupes:id,label_fr', 'departements:id,label_fr', 'attachments'])
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
        $data = $request->safe()->except(['roles', 'groupes', 'departements', 'photo', 'attachments']);

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

        // Sync groupes
        if ($request->filled('groupes')) {
            $user->groupes()->sync(
                Groupe::whereIn('label_fr', $request->groupes)->pluck('id')
            );
        }

        // Sync departements
        if ($request->filled('departements')) {
            $user->departements()->sync(
                Departement::whereIn('label_fr', $request->departements)->pluck('id')
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
            $user->load(['roles:id,name', 'groupes:id,label_fr', 'departements:id,label_fr', 'attachments'])
        );
    }

    /**
     * PUT /api/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $data = $request->safe()->except(['roles', 'groupes', 'departements', 'photo', 'attachments']);

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
                $oldRoles = $user->roles->pluck('name')->toArray();
                $user->roles()->sync(
                    Role::whereIn('name', $request->roles)->pluck('id')
                );
                $newRoles = $request->roles;
                if ($oldRoles != $newRoles) {
                    AuditLog::log(
                        'role_granted',
                        'roles',
                        $user,
                        ['roles' => $oldRoles],
                        ['roles' => $newRoles],
                        'success',
                        'Rôles modifiés pour ' . $user->full_name_fr
                    );
                }
            }

            // Sync groupes
            if ($request->has('groupes')) {
                $user->groupes()->sync(
                    Groupe::whereIn('label_fr', $request->groupes)->pluck('id')
                );
            }

            // Sync departements
            if ($request->has('departements')) {
                $user->departements()->sync(
                    Departement::whereIn('label_fr', $request->departements)->pluck('id')
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
        } catch (\Exception $e) {
            Log::error('User update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(
                ['message' => 'Failed to update user: ' . $e->getMessage()],
                500
            );
        }
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
     * POST /api/users/{user}/photo
     *
     * Upload photo du personnel
     */
    public function uploadPhoto(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:5120', // max 5MB
        ]);

        // Delete old photo
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        $user->update([
            'photo' => $request->file('photo')->store('personnel/photos', 'public'),
        ]);

        return response()->json(['photo' => $user->photo]);
    }

    /**
     * POST /api/users/{user}/attachments
     *
     * Upload pièces jointes du personnel
     */
    public function uploadAttachments(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'attachments' => 'required|array',
            'attachments.*' => 'file|max:5120', // max 5MB
        ]);

        $attachments = [];
        foreach ($request->file('attachments') ?? [] as $file) {
            $attachment = $user->attachments()->create([
                'name'      => $file->getClientOriginalName(),
                'type'      => $file->getClientMimeType(),
                'file_path' => $file->store('personnel/attachments', 'public'),
            ]);
            $attachments[] = $attachment;
        }

        return response()->json(['attachments' => $attachments]);
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

    /**
     * PUT /api/users/{user}/password
     *
     * Reset password (admin only)
     */
    public function updatePassword(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:4',
        ]);

        $user->update([
            'password' => bcrypt($request->password),
        ]);

        AuditLog::log(
            'password_change',
            'security',
            $user,
            null,
            null,
            'success',
            'Mot de passe modifié pour ' . $user->email
        );

        return response()->json(['message' => 'Mot de passe réinitialisé.']);
    }

    /**
     * POST /api/users/reorder
     *
     * Réordonner les utilisateurs par classement
     */
    public function reorder(Request $request): JsonResponse
    {
        $order = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|string',
            'order.*.classement' => 'required|string',
        ])['order'];

        foreach ($order as $item) {
            User::where('id', $item['id'])->update(['classement' => $item['classement']]);
        }

        return response()->json(['message' => 'Ordre mis à jour.']);
    }
}
