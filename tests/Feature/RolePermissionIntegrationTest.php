<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────

    private function createAdmin(): User
    {
        $perm = Permission::firstOrCreate(
            ['name' => 'admin.roles'],
            ['module' => 'admin', 'action' => 'roles']
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
        return ['Authorization' => 'Bearer ' . $user->createToken('t')->plainTextToken];
    }

    // ══════════════════════════════════════════════════════════════
    //  ROLE CRUD
    // ══════════════════════════════════════════════════════════════

    public function test_index_returns_roles_with_permissions(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/roles');

        $response->assertOk()
            ->assertJsonStructure([['id', 'name', 'permissions']]);
    }

    public function test_store_role(): void
    {
        $admin = $this->createAdmin();
        Permission::firstOrCreate(
            ['name' => 'ref.pays.view'],
            ['module' => 'ref.pays', 'action' => 'view']
        );

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/roles', [
                'name'         => 'editor',
                'display_name' => 'Éditeur',
                'description'  => 'Peut éditer des référentiels',
                'level'        => 50,
                'color'        => '#3b82f6',
                'permissions'  => ['ref.pays.view'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'editor');
        $this->assertDatabaseHas('roles', ['name' => 'editor']);
    }

    public function test_store_role_validation_unique_name(): void
    {
        $admin = $this->createAdmin();
        Role::firstOrCreate(['name' => 'duplicate'], ['level' => 10]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/roles', [
                'name'  => 'duplicate',
                'level' => 20,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_show_role(): void
    {
        $admin = $this->createAdmin();
        $role  = Role::firstOrCreate(['name' => 'viewer'], ['level' => 10]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson("/api/roles/{$role->id}");

        $response->assertOk()
            ->assertJsonPath('name', 'viewer');
    }

    public function test_update_role(): void
    {
        $admin = $this->createAdmin();
        $role  = Role::firstOrCreate(['name' => 'tester'], ['level' => 30]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/roles/{$role->id}", [
                'display_name' => 'Testeur',
                'level'        => 35,
                'color'        => '#10b981',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'level' => 35]);
    }

    public function test_destroy_role(): void
    {
        $admin = $this->createAdmin();
        $role  = Role::create(['name' => 'temp', 'level' => 5]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/roles/{$role->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_destroy_admin_role_forbidden(): void
    {
        $admin     = $this->createAdmin();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['level' => 250]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/roles/{$adminRole->id}");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Impossible de supprimer le rôle administrateur.']);
    }

    // ══════════════════════════════════════════════════════════════
    //  ALL PERMISSIONS
    // ══════════════════════════════════════════════════════════════

    public function test_all_permissions_grouped_by_module(): void
    {
        $admin = $this->createAdmin();
        Permission::firstOrCreate(
            ['name' => 'ref.pays.view'],
            ['module' => 'ref.pays', 'action' => 'view']
        );

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/permissions');

        $response->assertOk();
        $data = $response->json();
        // Should be grouped by module
        $this->assertIsArray($data);
        $this->assertArrayHasKey('admin', $data);
    }

    // ══════════════════════════════════════════════════════════════
    //  SYNC PERMISSIONS
    // ══════════════════════════════════════════════════════════════

    public function test_sync_permissions_add(): void
    {
        $admin = $this->createAdmin();
        $role  = Role::create(['name' => 'customrole', 'level' => 20]);
        $perm  = Permission::firstOrCreate(
            ['name' => 'ref.grades.view'],
            ['module' => 'ref.grades', 'action' => 'view']
        );

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/roles/{$role->id}/permissions", [
                'add'    => [$perm->id],
                'remove' => [],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('role_permission', [
            'role_id'       => $role->id,
            'permission_id' => $perm->id,
        ]);
    }

    public function test_sync_permissions_remove(): void
    {
        $admin = $this->createAdmin();
        $role  = Role::create(['name' => 'pruneable', 'level' => 15]);
        $perm  = Permission::firstOrCreate(
            ['name' => 'ref.fonctions.view'],
            ['module' => 'ref.fonctions', 'action' => 'view']
        );
        $role->permissions()->attach($perm->id);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/roles/{$role->id}/permissions", [
                'add'    => [],
                'remove' => [$perm->id],
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('role_permission', [
            'role_id'       => $role->id,
            'permission_id' => $perm->id,
        ]);
    }

    public function test_sync_permissions_validation(): void
    {
        $admin = $this->createAdmin();
        $role  = Role::create(['name' => 'validtest', 'level' => 10]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/roles/{$role->id}/permissions", [
                'add'    => [99999],
                'remove' => [],
            ]);

        $response->assertUnprocessable();
    }

    // ══════════════════════════════════════════════════════════════
    //  RBAC ACCESS CONTROL
    // ══════════════════════════════════════════════════════════════

    public function test_unauthorized_user_cannot_manage_roles(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/roles')
            ->assertForbidden();
    }
}
