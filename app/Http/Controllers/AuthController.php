<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     *
     * Authentifie l'utilisateur et retourne un token Sanctum + les données du profil.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            AuditLog::log(
                'login_failure',
                'auth',
                $user,
                null,
                ['email' => $request->email],
                'failure',
                'Tentative de connexion échouée pour ' . $request->email
            );
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        if ($user->status !== 'active') {
            AuditLog::log(
                'login_failure',
                'auth',
                $user,
                null,
                ['reason' => 'account_disabled'],
                'failure',
                'Tentative de connexion sur compte désactivé : ' . $user->email
            );
            return response()->json(
                ['message' => 'Ce compte est désactivé. Contactez un administrateur.'],
                403
            );
        }

        $token = $user->createToken('api-token')->plainTextToken;

        AuditLog::log(
            'login_success',
            'auth',
            $user,
            null,
            null,
            'success',
            'Connexion réussie pour ' . $user->email
        );

        return response()->json([
            'token' => $token,
            'user'  => $this->buildUserPayload($user),
        ]);
    }

    /**
     * POST /api/auth/logout
     *
     * Révoque le token courant.
     */
    public function logout(Request $request): JsonResponse
    {
        AuditLog::log(
            'logout',
            'auth',
            $request->user(),
            null,
            null,
            'success',
            'Déconnexion de ' . $request->user()->email
        );

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    /**
     * GET /api/auth/me
     *
     * Retourne le profil de l'utilisateur connecté.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->buildUserPayload($request->user()));
    }

    /**
     * POST /api/auth/forgot-password
     *
     * Envoie un email de réinitialisation de mot de passe.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => __($status)]);
    }

    // ── Privé ─────────────────────────────────────────────────────────────

    /**
     * Construit le payload utilisateur (roles + permissions inclus).
     *
     * @return array<string, mixed>
     */
    private function buildUserPayload(User $user): array
    {
        $user->loadMissing('roles.permissions');

        return [
            'id'          => $user->id,
            'name'        => $user->login ?? $user->full_name_fr ?? $user->email,
            'email'       => $user->email,
            'status'      => $user->status,
            'roles'       => $user->roles->pluck('name'),
            'permissions' => $user->allPermissions(),
        ];
    }
}
