<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests d'audit de sécurité — peuvent être lancés en dev ou pré-prod.
 *
 * Couvrent : security headers, rate limiting, authentification,
 * validation des mots de passe, mass assignment, et expiration des tokens.
 *
 * Lancer : php artisan test --filter=SecurityAuditTest
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────

    private function createAdminUser(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'admin.users'],
            ['module' => 'admin', 'action' => 'users']
        );
        $settingsPerm = Permission::firstOrCreate(
            ['name' => 'admin.settings'],
            ['module' => 'admin', 'action' => 'settings']
        );
        $role = Role::firstOrCreate(
            ['name' => 'superadmin'],
            ['level' => 200, 'color' => '#ef4444']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id, $settingsPerm->id]);

        $user = User::factory()->create([
            'email'    => 'admin-test@avocatique.ma',
            'password' => 'SecurePass1',
            'is_admin' => true,
            'status'   => 'active',
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createRegularUser(string $email = 'user@avocatique.ma'): User
    {
        return User::factory()->create([
            'email'    => $email,
            'password' => 'UserPass123',
            'is_admin' => false,
            'status'   => 'active',
        ]);
    }

    private function loginAs(User $user): string
    {
        return $user->createToken('test-token')->plainTextToken;
    }

    // ══════════════════════════════════════════════════════════════════
    //  1. SECURITY HEADERS
    // ══════════════════════════════════════════════════════════════════

    public function test_security_headers_on_public_route(): void
    {
        $response = $this->getJson('/api/translations');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_security_headers_on_401_response(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_security_headers_on_authenticated_route(): void
    {
        $user  = $this->createAdminUser();
        $token = $this->loginAs($user);

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    // ══════════════════════════════════════════════════════════════════
    //  2. RATE LIMITING — LOGIN
    // ══════════════════════════════════════════════════════════════════

    public function test_login_rate_limited_after_5_attempts(): void
    {
        $payload = ['email' => 'brute@force.com', 'password' => 'wrong'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $payload)->assertStatus(422);
        }

        // 6e tentative → 429
        $response = $this->postJson('/api/auth/login', $payload);
        $response->assertStatus(429);
    }

    public function test_login_rate_limit_headers_present(): void
    {
        $payload = ['email' => 'headers@test.com', 'password' => 'wrong'];

        // Épuiser le quota
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $payload);
        }

        $response = $this->postJson('/api/auth/login', $payload);
        $response->assertStatus(429);
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('Retry-After');
    }

    // ══════════════════════════════════════════════════════════════════
    //  3. RATE LIMITING — FORGOT PASSWORD
    // ══════════════════════════════════════════════════════════════════

    public function test_forgot_password_rate_limited_after_3_attempts(): void
    {
        $payload = ['email' => 'forgot@test.com'];

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/forgot-password', $payload);
        }

        // 4e tentative → 429
        $response = $this->postJson('/api/auth/forgot-password', $payload);
        $response->assertStatus(429);
    }

    // ══════════════════════════════════════════════════════════════════
    //  4. API RATE LIMITING (authenticated)
    // ══════════════════════════════════════════════════════════════════

    public function test_api_rate_limit_header_present_on_authenticated_request(): void
    {
        $user  = $this->createAdminUser();
        $token = $this->loginAs($user);

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit', '120');
    }

    // ══════════════════════════════════════════════════════════════════
    //  5. PASSWORD — NO DOUBLE HASHING
    // ══════════════════════════════════════════════════════════════════

    public function test_password_reset_allows_login_with_new_password(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->loginAs($admin);
        $target = $this->createRegularUser();

        $newPassword = 'Changed8new';

        // Reset mot de passe
        $this->putJson("/api/users/{$target->id}/password", [
            'password' => $newPassword,
        ], ['Authorization' => "Bearer $token"])->assertOk();

        // Login avec le nouveau mot de passe
        $response = $this->postJson('/api/auth/login', [
            'email'    => $target->email,
            'password' => $newPassword,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user']);
    }

    public function test_no_double_hashing_in_database(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->loginAs($admin);
        $target = $this->createRegularUser();

        $rawPassword = 'MyPassword8test';

        $this->putJson("/api/users/{$target->id}/password", [
            'password' => $rawPassword,
        ], ['Authorization' => "Bearer $token"])->assertOk();

        $target->refresh();

        // Le hash doit être vérifiable directement
        $this->assertTrue(
            Hash::check($rawPassword, $target->password),
            'Le mot de passe ne devrait pas être doublement hashé'
        );
    }

    // ══════════════════════════════════════════════════════════════════
    //  6. PASSWORD — STRENGTH VALIDATION
    // ══════════════════════════════════════════════════════════════════

    public function test_password_too_short_rejected(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->loginAs($admin);
        $target = $this->createRegularUser();

        $response = $this->putJson("/api/users/{$target->id}/password", [
            'password' => 'Ab1',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_password_without_numbers_rejected(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->loginAs($admin);
        $target = $this->createRegularUser();

        $response = $this->putJson("/api/users/{$target->id}/password", [
            'password' => 'abcdefghij',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_password_without_letters_rejected(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->loginAs($admin);
        $target = $this->createRegularUser();

        $response = $this->putJson("/api/users/{$target->id}/password", [
            'password' => '123456789',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_strong_password_accepted(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->loginAs($admin);
        $target = $this->createRegularUser();

        $response = $this->putJson("/api/users/{$target->id}/password", [
            'password' => 'Strong8pass',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertOk();
    }

    // ══════════════════════════════════════════════════════════════════
    //  7. SANCTUM TOKEN EXPIRATION
    // ══════════════════════════════════════════════════════════════════

    public function test_sanctum_token_expiration_is_configured(): void
    {
        $expiration = config('sanctum.expiration');

        $this->assertNotNull($expiration, 'Sanctum token expiration ne doit pas être null');
        $this->assertGreaterThan(0, $expiration, 'Sanctum token expiration doit être > 0');
        $this->assertLessThanOrEqual(
            480,
            $expiration,
            'Sanctum token expiration ne doit pas dépasser 8 heures (480 min)'
        );
    }

    // ══════════════════════════════════════════════════════════════════
    //  8. MASS ASSIGNMENT — TFA_SECRET PROTECTION
    // ══════════════════════════════════════════════════════════════════

    public function test_tfa_secret_cannot_be_set_via_user_update(): void
    {
        $admin  = $this->createAdminUser();
        $token  = $this->loginAs($admin);
        $target = $this->createRegularUser();

        $this->putJson("/api/users/{$target->id}", [
            'full_name_fr' => 'Test User',
            'tfa_secret'   => 'INJECTED_SECRET_VALUE',
        ], ['Authorization' => "Bearer $token"]);

        $target->refresh();
        $this->assertNull(
            $target->tfa_secret,
            'tfa_secret ne doit pas être modifiable via l\'API user update'
        );
    }

    // ══════════════════════════════════════════════════════════════════
    //  9. MASS ASSIGNMENT — TRANSLATION VALIDATED DATA ONLY
    // ══════════════════════════════════════════════════════════════════

    public function test_translation_store_ignores_extra_fields(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->loginAs($admin);

        $response = $this->postJson('/api/translations-crud', [
            'code'            => 'test.security.extra',
            'libelle_fr'      => 'FR',
            'libelle_ar'      => 'AR',
            'libelle_en'      => 'EN',
            'malicious_field' => 'EVIL',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(201);
        $response->assertJsonMissing(['malicious_field' => 'EVIL']);

        // Vérifier en base aussi
        $translation = Translation::where('code', 'test.security.extra')->first();
        $this->assertNotNull($translation);
        $this->assertArrayNotHasKey('malicious_field', $translation->getAttributes());
    }

    public function test_translation_update_ignores_extra_fields(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->loginAs($admin);

        $translation = Translation::create([
            'code'       => 'test.security.update',
            'libelle_fr' => 'FR',
            'libelle_ar' => 'AR',
            'libelle_en' => 'EN',
        ]);

        $response = $this->putJson("/api/translations-crud/{$translation->id}", [
            'code'            => 'test.security.update',
            'libelle_fr'      => 'FR Updated',
            'libelle_ar'      => 'AR',
            'libelle_en'      => 'EN',
            'malicious_field' => 'INJECTED',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertOk();
        $response->assertJsonMissing(['malicious_field' => 'INJECTED']);
    }

    // ══════════════════════════════════════════════════════════════════
    //  10. AUTH — ROUTE PROTECTION
    // ══════════════════════════════════════════════════════════════════

    public function test_protected_routes_return_401_without_token(): void
    {
        $endpoints = [
            ['GET',  '/api/auth/me'],
            ['GET',  '/api/users'],
            ['GET',  '/api/roles'],
            ['GET',  '/api/translations/paginated'],
            ['GET',  '/api/menu'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401, "Route $method $uri devrait retourner 401");
        }
    }

    public function test_public_translations_accessible_without_token(): void
    {
        $response = $this->getJson('/api/translations');
        $response->assertOk();
    }

    // ══════════════════════════════════════════════════════════════════
    //  11. AUTH — NO USER ENUMERATION
    // ══════════════════════════════════════════════════════════════════

    public function test_login_returns_generic_error_for_wrong_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nonexistent@fake.com',
            'password' => 'WrongPass1',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'errors' => ['email' => ['Identifiants incorrects.']],
        ]);
    }

    public function test_login_returns_same_error_for_wrong_password(): void
    {
        $user = $this->createRegularUser('existing@test.com');

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'WrongPass1',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'errors' => ['email' => ['Identifiants incorrects.']],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    //  12. AUTH — DISABLED ACCOUNT
    // ══════════════════════════════════════════════════════════════════

    public function test_disabled_account_cannot_login(): void
    {
        $user = User::factory()->create([
            'email'    => 'disabled@test.com',
            'password' => 'ValidPass8',
            'status'   => 'inactive',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'disabled@test.com',
            'password' => 'ValidPass8',
        ]);

        $response->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════════
    //  13. RBAC — PERMISSION ENFORCEMENT
    // ══════════════════════════════════════════════════════════════════

    public function test_user_without_permission_gets_403(): void
    {
        $user  = $this->createRegularUser('noperm@test.com');
        $token = $this->loginAs($user);

        $response = $this->getJson('/api/users', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════════
    //  14. LOGOUT — TOKEN REVOCATION
    // ══════════════════════════════════════════════════════════════════

    public function test_logout_revokes_token(): void
    {
        $user  = $this->createAdminUser();
        $token = $this->loginAs($user);

        // Logout
        $this->postJson('/api/auth/logout', [], [
            'Authorization' => "Bearer $token",
        ])->assertOk();

        // Vérifier que le token a bien été supprimé en base
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id'   => $user->id,
            'tokenable_type' => User::class,
        ]);
    }
}
