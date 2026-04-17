<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────

    private function createAdmin(): User
    {
        $perm = Permission::firstOrCreate(
            ['name' => 'admin.settings'],
            ['module' => 'admin', 'action' => 'settings']
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
    //  INDEX (admin flat list)
    // ══════════════════════════════════════════════════════════════

    public function test_index_returns_flat_list(): void
    {
        $admin = $this->createAdmin();
        MenuItem::create(['label_fr' => 'Dashboard', 'icon' => 'home', 'route' => '/dashboard', 'ordre' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/menu');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json()));
    }

    // ══════════════════════════════════════════════════════════════
    //  TREE (role-filtered)
    // ══════════════════════════════════════════════════════════════

    public function test_tree_admin_sees_all(): void
    {
        $admin = $this->createAdmin();
        MenuItem::create(['label_fr' => 'Visible', 'is_visible' => true, 'ordre' => 0]);
        MenuItem::create(['label_fr' => 'Hidden', 'is_visible' => false, 'ordre' => 1]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/menu/tree');

        // Admin should see visible items (hidden ones excluded by visible scope)
        $response->assertOk();
    }

    public function test_tree_user_sees_only_role_menus(): void
    {
        $role = Role::firstOrCreate(['name' => 'viewer'], ['level' => 10]);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->sync([$role->id]);

        $menu = MenuItem::create([
            'label_fr'   => 'ViewerMenu',
            'is_visible' => true,
            'ordre'      => 0,
        ]);
        $menu->roles()->sync([$role->id]);

        $other = MenuItem::create([
            'label_fr'   => 'AdminOnly',
            'is_visible' => true,
            'ordre'      => 1,
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['level' => 255]);
        $other->roles()->sync([$adminRole->id]);

        $token = $user->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/menu/tree');

        $response->assertOk();
        $labels = collect($response->json())->pluck('label_fr');
        $this->assertTrue($labels->contains('ViewerMenu'));
        $this->assertFalse($labels->contains('AdminOnly'));
    }

    // ══════════════════════════════════════════════════════════════
    //  STORE
    // ══════════════════════════════════════════════════════════════

    public function test_store_menu_item(): void
    {
        $admin = $this->createAdmin();
        $role  = Role::firstOrCreate(['name' => 'viewer'], ['level' => 10]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/menu', [
                'label_fr'   => 'Référentiels',
                'label_ar'   => 'المراجع',
                'label_en'   => 'References',
                'icon'       => 'book',
                'route'      => '/referentiels',
                'is_visible' => true,
                'module'     => 'referentiels',
                'roles'      => ['viewer'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('label_fr', 'Référentiels');
        $this->assertDatabaseHas('menu_items', ['label_fr' => 'Référentiels']);
    }

    public function test_store_child_menu_item(): void
    {
        $admin  = $this->createAdmin();
        $parent = MenuItem::create(['label_fr' => 'Parent', 'ordre' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/menu', [
                'parent_id' => $parent->id,
                'label_fr'  => 'Enfant',
            ]);

        $response->assertCreated()
            ->assertJsonPath('parent_id', $parent->id);
    }

    public function test_store_auto_increments_ordre(): void
    {
        $admin = $this->createAdmin();
        MenuItem::create(['label_fr' => 'First', 'ordre' => 0]);
        MenuItem::create(['label_fr' => 'Second', 'ordre' => 1]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/menu', ['label_fr' => 'Third']);

        $response->assertCreated();
        $this->assertEquals(2, $response->json('ordre'));
    }

    // ══════════════════════════════════════════════════════════════
    //  SHOW
    // ══════════════════════════════════════════════════════════════

    public function test_show_includes_children_and_roles(): void
    {
        $admin  = $this->createAdmin();
        $parent = MenuItem::create(['label_fr' => 'Root', 'ordre' => 0]);
        MenuItem::create(['label_fr' => 'Child', 'parent_id' => $parent->id, 'ordre' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson("/api/menu/{$parent->id}");

        $response->assertOk()
            ->assertJsonPath('label_fr', 'Root')
            ->assertJsonStructure(['children', 'roles']);
    }

    // ══════════════════════════════════════════════════════════════
    //  UPDATE
    // ══════════════════════════════════════════════════════════════

    public function test_update_menu_item(): void
    {
        $admin = $this->createAdmin();
        $menu  = MenuItem::create(['label_fr' => 'Old', 'ordre' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/menu/{$menu->id}", [
                'label_fr' => 'Updated',
                'icon'     => 'edit',
            ]);

        $response->assertOk()
            ->assertJsonPath('label_fr', 'Updated');
    }

    // ══════════════════════════════════════════════════════════════
    //  DESTROY
    // ══════════════════════════════════════════════════════════════

    public function test_destroy_menu_item(): void
    {
        $admin = $this->createAdmin();
        $menu  = MenuItem::create(['label_fr' => 'ToDelete', 'ordre' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/menu/{$menu->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('menu_items', ['id' => $menu->id]);
    }

    public function test_destroy_cascades_to_children(): void
    {
        $admin  = $this->createAdmin();
        $parent = MenuItem::create(['label_fr' => 'Parent', 'ordre' => 0]);
        $child  = MenuItem::create(['label_fr' => 'Child', 'parent_id' => $parent->id, 'ordre' => 0]);

        $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/menu/{$parent->id}")
            ->assertOk();

        $this->assertDatabaseMissing('menu_items', ['id' => $child->id]);
    }

    // ══════════════════════════════════════════════════════════════
    //  TOGGLE VISIBILITY
    // ══════════════════════════════════════════════════════════════

    public function test_toggle_visibility(): void
    {
        $admin = $this->createAdmin();
        $menu  = MenuItem::create(['label_fr' => 'Vis', 'is_visible' => true, 'ordre' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->patchJson("/api/menu/{$menu->id}/toggle-visibility");

        $response->assertOk();
        $this->assertFalse((bool) $response->json('is_visible'));
    }

    // ══════════════════════════════════════════════════════════════
    //  REORDER
    // ══════════════════════════════════════════════════════════════

    public function test_reorder_menu_items(): void
    {
        $admin = $this->createAdmin();
        $m1    = MenuItem::create(['label_fr' => 'A', 'ordre' => 0]);
        $m2    = MenuItem::create(['label_fr' => 'B', 'ordre' => 1]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/menu/reorder', [
                'items' => [
                    ['id' => $m1->id, 'ordre' => 5],
                    ['id' => $m2->id, 'ordre' => 0],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('menu_items', ['id' => $m1->id, 'ordre' => 5]);
        $this->assertDatabaseHas('menu_items', ['id' => $m2->id, 'ordre' => 0]);
    }

    // ══════════════════════════════════════════════════════════════
    //  ACCESS CONTROL
    // ══════════════════════════════════════════════════════════════

    public function test_unauthenticated_cannot_access_menu_admin(): void
    {
        $this->getJson('/api/menu')->assertUnauthorized();
        $this->postJson('/api/menu', ['label_fr' => 'X'])->assertUnauthorized();
    }

    public function test_unpermissioned_user_cannot_manage_menu(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/menu')
            ->assertForbidden();
    }
}
