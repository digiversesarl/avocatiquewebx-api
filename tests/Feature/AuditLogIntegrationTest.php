<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogIntegrationTest extends TestCase
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

    private function seedLogs(): void
    {
        AuditLog::create([
            'user_id'    => 1,
            'user_name'  => 'Admin',
            'action'     => 'login_success',
            'category'   => 'security',
            'result'     => 'success',
            'ip_address' => '127.0.0.1',
        ]);
        AuditLog::create([
            'user_id'    => null,
            'user_name'  => 'Inconnu',
            'action'     => 'login_failure',
            'category'   => 'security',
            'result'     => 'failure',
            'ip_address' => '192.168.1.1',
        ]);
        AuditLog::create([
            'user_id'         => 1,
            'user_name'       => 'Admin',
            'action'          => 'record_created',
            'category'        => 'data',
            'auditable_type'  => 'App\\Models\\Pays',
            'auditable_id'    => 1,
            'auditable_label' => 'Maroc',
            'result'          => 'success',
            'ip_address'      => '127.0.0.1',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  INDEX (paginated + filtres)
    // ══════════════════════════════════════════════════════════════

    public function test_index_returns_paginated_logs(): void
    {
        $admin = $this->createAdmin();
        $this->seedLogs();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/audit-logs');

        $response->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total']);
        // At least 3 seeded logs (plus auto-created from admin setup)
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
    }

    public function test_index_filter_by_category(): void
    {
        $admin = $this->createAdmin();
        $this->seedLogs();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/audit-logs?category=security');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, $response->json('total'));
    }

    public function test_index_filter_by_action(): void
    {
        $admin = $this->createAdmin();
        $this->seedLogs();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/audit-logs?action=login_failure');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_index_filter_by_search(): void
    {
        $admin = $this->createAdmin();
        $this->seedLogs();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/audit-logs?search=Maroc');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_index_filter_by_result(): void
    {
        $admin = $this->createAdmin();
        $this->seedLogs();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/audit-logs?result=failure');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_index_filter_by_date_range(): void
    {
        $admin = $this->createAdmin();
        $this->seedLogs();

        $today = now()->format('Y-m-d');
        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson("/api/audit-logs?date_from={$today}&date_to={$today}");

        $response->assertOk();
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
    }

    public function test_index_sorting(): void
    {
        $admin = $this->createAdmin();
        $this->seedLogs();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/audit-logs?sort_by=action&sort_dir=asc');

        $response->assertOk();
        $actions = collect($response->json('data'))->pluck('action')->toArray();
        $this->assertEquals($actions, collect($actions)->sort()->values()->toArray());
    }

    public function test_index_per_page(): void
    {
        $admin = $this->createAdmin();
        $this->seedLogs();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/audit-logs?per_page=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
    }

    // ══════════════════════════════════════════════════════════════
    //  SHOW
    // ══════════════════════════════════════════════════════════════

    public function test_show_audit_log(): void
    {
        $admin = $this->createAdmin();
        $log   = AuditLog::create([
            'user_name'  => 'Test',
            'action'     => 'record_created',
            'category'   => 'data',
            'result'     => 'success',
            'old_values' => ['name' => 'old'],
            'new_values' => ['name' => 'new'],
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson("/api/audit-logs/{$log->id}");

        $response->assertOk()
            ->assertJsonPath('data.action', 'record_created')
            ->assertJsonPath('data.old_values.name', 'old')
            ->assertJsonPath('data.new_values.name', 'new');
    }

    // ══════════════════════════════════════════════════════════════
    //  USERS (distinct list)
    // ══════════════════════════════════════════════════════════════

    public function test_users_endpoint(): void
    {
        $admin = $this->createAdmin();
        $this->seedLogs();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/audit-logs/users');

        $response->assertOk();
        $names = collect($response->json())->pluck('user_name');
        $this->assertTrue($names->contains('Admin'));
    }

    // ══════════════════════════════════════════════════════════════
    //  STATS
    // ══════════════════════════════════════════════════════════════

    public function test_stats(): void
    {
        $admin = $this->createAdmin();
        $this->seedLogs();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/audit-logs/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'total',
                'today',
                'failed_logins_24h',
                'password_resets_7d',
                'role_changes_7d',
                'records_created_7d',
                'records_updated_7d',
                'records_deleted_7d',
            ]);
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
        $this->assertGreaterThanOrEqual(1, $response->json('failed_logins_24h'));
        $this->assertGreaterThanOrEqual(1, $response->json('records_created_7d'));
    }

    // ══════════════════════════════════════════════════════════════
    //  AUDIT TRAIL (automatic logging)
    // ══════════════════════════════════════════════════════════════

    public function test_audit_trail_on_login(): void
    {
        $user = User::factory()->create([
            'email'    => 'audit@avocatique.ma',
            'password' => 'SecurePass1',
            'status'   => 'active',
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'audit@avocatique.ma',
            'password' => 'SecurePass1',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action'   => 'login_success',
            'category' => 'auth',
        ]);
    }

    public function test_audit_trail_on_failed_login(): void
    {
        User::factory()->create([
            'email'    => 'fail@avocatique.ma',
            'password' => 'SecurePass1',
            'status'   => 'active',
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'fail@avocatique.ma',
            'password' => 'WrongPass1',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('audit_logs', [
            'action'   => 'login_failure',
            'category' => 'auth',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  ACCESS CONTROL
    // ══════════════════════════════════════════════════════════════

    public function test_unpermissioned_user_cannot_view_audit(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_view_audit(): void
    {
        $this->getJson('/api/audit-logs')->assertUnauthorized();
    }
}
