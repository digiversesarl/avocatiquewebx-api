<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────

    private function createActiveUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email'    => 'user@avocatique.ma',
            'password' => 'SecurePass1',
            'status'   => 'active',
        ], $overrides));
    }

    private function loginAs(User $user): string
    {
        return $user->createToken('test-token')->plainTextToken;
    }

    // ══════════════════════════════════════════════════════════════
    //  LOGIN
    // ══════════════════════════════════════════════════════════════

    public function test_login_success(): void
    {
        $user = $this->createActiveUser();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@avocatique.ma',
            'password' => 'SecurePass1',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'status']]);
    }

    public function test_login_returns_user_roles_and_permissions(): void
    {
        $perm = Permission::firstOrCreate(
            ['name' => 'admin.users'],
            ['module' => 'admin', 'action' => 'users']
        );
        $role = Role::firstOrCreate(
            ['name' => 'manager'],
            ['level' => 100]
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = $this->createActiveUser();
        $user->roles()->sync([$role->id]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@avocatique.ma',
            'password' => 'SecurePass1',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.roles.0', 'manager')
            ->assertJsonStructure(['user' => ['permissions']]);
    }

    public function test_login_wrong_password(): void
    {
        $this->createActiveUser();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@avocatique.ma',
            'password' => 'WrongPassword1',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_wrong_email(): void
    {
        $this->createActiveUser();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'unknown@avocatique.ma',
            'password' => 'SecurePass1',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_inactive_user(): void
    {
        $this->createActiveUser(['status' => 'inactive']);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@avocatique.ma',
            'password' => 'SecurePass1',
        ]);

        $response->assertForbidden();
    }

    public function test_login_validation_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    // ══════════════════════════════════════════════════════════════
    //  LOGOUT
    // ══════════════════════════════════════════════════════════════

    public function test_logout_revokes_token(): void
    {
        $user  = $this->createActiveUser();
        $token = $this->loginAs($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id'   => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_logout_unauthenticated(): void
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }

    // ══════════════════════════════════════════════════════════════
    //  ME
    // ══════════════════════════════════════════════════════════════

    public function test_me_returns_current_user(): void
    {
        $user  = $this->createActiveUser();
        $token = $this->loginAs($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_me_unauthenticated(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    // ══════════════════════════════════════════════════════════════
    //  FORGOT PASSWORD
    // ══════════════════════════════════════════════════════════════

    public function test_forgot_password_with_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@avocatique.ma',
        ]);

        // Email doesn't exist → validation error
        $response->assertUnprocessable();
    }

    public function test_forgot_password_validation(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
