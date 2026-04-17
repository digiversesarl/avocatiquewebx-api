<?php

namespace Tests\Feature;

use App\Models\Departement;
use App\Models\Groupe;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────

    private function createAdminWithPermission(): User
    {
        $perm = Permission::firstOrCreate(
            ['name' => 'admin.users'],
            ['module' => 'admin', 'action' => 'users']
        );
        $role = Role::firstOrCreate(
            ['name' => 'superadmin'],
            ['level' => 200, 'color' => '#ef4444']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = User::factory()->create([
            'email'    => 'admin@avocatique.ma',
            'password' => 'SecurePass1',
            'is_admin' => true,
            'status'   => 'active',
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function authHeader(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}"];
    }

    // ══════════════════════════════════════════════════════════════
    //  INDEX
    // ══════════════════════════════════════════════════════════════

    public function test_index_returns_paginated_users(): void
    {
        $admin = $this->createAdminWithPermission();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/users');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_index_search_filter(): void
    {
        $admin = $this->createAdminWithPermission();
        User::factory()->create(['full_name_fr' => 'TestUnique', 'status' => 'active']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/users?search=TestUnique');

        $response->assertOk();
        $this->assertTrue(
            collect($response->json('data'))->contains(fn ($u) => $u['full_name_fr'] === 'TestUnique')
        );
    }

    public function test_index_denied_without_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users')
            ->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════
    //  STORE
    // ══════════════════════════════════════════════════════════════

    public function test_store_creates_user(): void
    {
        $admin = $this->createAdminWithPermission();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/users', [
                'email'        => 'newuser@avocatique.ma',
                'password'     => 'NewPass123',
                'full_name_fr' => 'Nouvel Utilisateur',
                'full_name_ar' => 'مستخدم جديد',
                'login'        => 'newuser',
                'status'       => 'active',
            ]);

        $response->assertCreated()
            ->assertJsonPath('email', 'newuser@avocatique.ma');
        $this->assertDatabaseHas('users', ['email' => 'newuser@avocatique.ma']);
    }

    public function test_store_syncs_roles(): void
    {
        $admin = $this->createAdminWithPermission();
        $role  = Role::firstOrCreate(['name' => 'editor'], ['level' => 50]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/users', [
                'email'        => 'roleuser@avocatique.ma',
                'password'     => 'RolePass1',
                'full_name_fr' => 'Rôle User',
                'full_name_ar' => 'دور',
                'login'        => 'roleuser',
                'status'       => 'active',
                'roles'        => ['editor'],
            ]);

        $response->assertCreated();
        $created = User::where('email', 'roleuser@avocatique.ma')->first();
        $this->assertTrue($created->roles->contains('name', 'editor'));
    }

    public function test_store_validation_unique_email(): void
    {
        $admin = $this->createAdminWithPermission();
        User::factory()->create(['email' => 'existing@avocatique.ma', 'status' => 'active']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/users', [
                'email'        => 'existing@avocatique.ma',
                'password'     => 'Pass1234',
                'full_name_fr' => 'Dup',
                'full_name_ar' => 'مكرر',
                'login'        => 'dup',
                'status'       => 'active',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ══════════════════════════════════════════════════════════════
    //  SHOW
    // ══════════════════════════════════════════════════════════════

    public function test_show_returns_user(): void
    {
        $admin  = $this->createAdminWithPermission();
        $target = User::factory()->create(['status' => 'active']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson("/api/users/{$target->id}");

        $response->assertOk()
            ->assertJsonPath('id', $target->id);
    }

    public function test_show_not_found(): void
    {
        $admin = $this->createAdminWithPermission();

        $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/users/99999')
            ->assertNotFound();
    }

    // ══════════════════════════════════════════════════════════════
    //  UPDATE
    // ══════════════════════════════════════════════════════════════

    public function test_update_user(): void
    {
        $admin  = $this->createAdminWithPermission();
        $target = User::factory()->create(['status' => 'active', 'full_name_fr' => 'Old Name']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/users/{$target->id}", [
                'email'        => $target->email,
                'full_name_fr' => 'New Name',
                'full_name_ar' => $target->full_name_ar,
                'login'        => $target->login,
                'status'       => 'active',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'full_name_fr' => 'New Name']);
    }

    // ══════════════════════════════════════════════════════════════
    //  DELETE
    // ══════════════════════════════════════════════════════════════

    public function test_destroy_soft_deletes_user(): void
    {
        $admin  = $this->createAdminWithPermission();
        $target = User::factory()->create(['status' => 'active']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/users/{$target->id}");

        $response->assertOk();
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_destroy_cannot_delete_self(): void
    {
        $admin = $this->createAdminWithPermission();

        $response = $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/users/{$admin->id}");

        $response->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════
    //  TOGGLE ACTIVE
    // ══════════════════════════════════════════════════════════════

    public function test_toggle_active(): void
    {
        $admin  = $this->createAdminWithPermission();
        $target = User::factory()->create(['status' => 'active', 'active' => true]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->patchJson("/api/users/{$target->id}/toggle-active");

        $response->assertOk();
        $target->refresh();
        $this->assertFalse((bool) $target->active);
    }

    // ══════════════════════════════════════════════════════════════
    //  UPDATE PASSWORD
    // ══════════════════════════════════════════════════════════════

    public function test_update_password(): void
    {
        $admin  = $this->createAdminWithPermission();
        $target = User::factory()->create(['status' => 'active']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/users/{$target->id}/password", [
                'password' => 'NewSecure1',
            ]);

        $response->assertOk();
    }

    public function test_update_password_validation(): void
    {
        $admin  = $this->createAdminWithPermission();
        $target = User::factory()->create(['status' => 'active']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/users/{$target->id}/password", [
                'password' => 'short',
            ]);

        $response->assertUnprocessable();
    }

    // ══════════════════════════════════════════════════════════════
    //  PHOTO UPLOAD
    // ══════════════════════════════════════════════════════════════

    public function test_upload_photo(): void
    {
        Storage::fake('public');
        $admin  = $this->createAdminWithPermission();
        $target = User::factory()->create(['status' => 'active']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson("/api/users/{$target->id}/photo", [
                'photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
            ]);

        $response->assertOk();
        $target->refresh();
        $this->assertNotNull($target->photo);
    }

    // ══════════════════════════════════════════════════════════════
    //  ATTACHMENTS
    // ══════════════════════════════════════════════════════════════

    public function test_upload_attachments(): void
    {
        Storage::fake('public');
        $admin  = $this->createAdminWithPermission();
        $target = User::factory()->create(['status' => 'active']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson("/api/users/{$target->id}/attachments", [
                'attachments' => [
                    UploadedFile::fake()->create('doc.pdf', 100),
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('user_attachments', ['user_id' => $target->id]);
    }

    public function test_delete_attachment(): void
    {
        Storage::fake('public');
        $admin  = $this->createAdminWithPermission();
        $target = User::factory()->create(['status' => 'active']);
        $att    = $target->attachments()->create([
            'name'      => 'test.pdf',
            'type'      => 'application/pdf',
            'file_path' => 'personnel/attachments/test.pdf',
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/users/{$target->id}/attachments/{$att->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('user_attachments', ['id' => $att->id]);
    }

    // ══════════════════════════════════════════════════════════════
    //  REORDER
    // ══════════════════════════════════════════════════════════════

    public function test_reorder_users(): void
    {
        $admin = $this->createAdminWithPermission();
        $u1    = User::factory()->create(['status' => 'active', 'classement' => 0]);
        $u2    = User::factory()->create(['status' => 'active', 'classement' => 1]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/users/reorder', [
                'order' => [
                    ['id' => (string) $u1->id, 'classement' => '2'],
                    ['id' => (string) $u2->id, 'classement' => '0'],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $u1->id, 'classement' => 2]);
        $this->assertDatabaseHas('users', ['id' => $u2->id, 'classement' => 0]);
    }
}
