<?php

namespace Tests\Feature;

use App\Models\Departement;
use App\Models\Fonction;
use App\Models\Grade;
use App\Models\Groupe;
use App\Models\Pays;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests d'intégration pour les référentiels (Pays, Villes, Fonctions, Grades, Départements, Groupes).
 * Utilise Pays comme cas principal exhaustif, puis vérifie le CRUD basique pour chaque autre.
 */
class ReferentielIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────

    private function createAdminForRef(string $refName): User
    {
        $perm = Permission::firstOrCreate(
            ['name' => "ref.{$refName}.view"],
            ['module' => "ref.{$refName}", 'action' => 'view']
        );
        $createPerm = Permission::firstOrCreate(
            ['name' => "ref.{$refName}.create"],
            ['module' => "ref.{$refName}", 'action' => 'create']
        );
        $editPerm = Permission::firstOrCreate(
            ['name' => "ref.{$refName}.edit"],
            ['module' => "ref.{$refName}", 'action' => 'edit']
        );
        $deletePerm = Permission::firstOrCreate(
            ['name' => "ref.{$refName}.delete"],
            ['module' => "ref.{$refName}", 'action' => 'delete']
        );

        $role = Role::firstOrCreate(
            ['name' => 'superadmin'],
            ['level' => 200, 'color' => '#ef4444']
        );
        $role->permissions()->syncWithoutDetaching([
            $perm->id, $createPerm->id, $editPerm->id, $deletePerm->id,
        ]);

        $user = User::factory()->create([
            'email'    => 'admin@avocatique.ma',
            'password' => 'Pass1234',
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
    //  PAYS — Full CRUD + toggle + duplicate + reorder
    // ══════════════════════════════════════════════════════════════

    public function test_pays_index(): void
    {
        $admin = $this->createAdminForRef('pays');
        Pays::create(['code' => 'MA', 'label_fr' => 'Maroc', 'label_ar' => 'المغرب', 'label_en' => 'Morocco', 'classement' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/pays');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_pays_index_search(): void
    {
        $admin = $this->createAdminForRef('pays');
        Pays::create(['code' => 'MA', 'label_fr' => 'Maroc', 'label_ar' => 'المغرب', 'label_en' => 'Morocco', 'classement' => 0]);
        Pays::create(['code' => 'FR', 'label_fr' => 'France', 'label_ar' => 'فرنسا', 'label_en' => 'France', 'classement' => 1]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/pays?search=Maroc');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_pays_index_status_filter(): void
    {
        $admin = $this->createAdminForRef('pays');
        Pays::create(['code' => 'MA', 'label_fr' => 'Maroc', 'label_ar' => 'المغرب', 'label_en' => 'Morocco', 'is_active' => true, 'classement' => 0]);
        Pays::create(['code' => 'XX', 'label_fr' => 'Inactif', 'label_ar' => 'غير نشط', 'label_en' => 'Inactive', 'is_active' => false, 'classement' => 1]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/pays?status=active');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_pays_store(): void
    {
        $admin = $this->createAdminForRef('pays');

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/pays', [
                'code'     => 'TN',
                'label_fr' => 'Tunisie',
                'label_ar' => 'تونس',
                'label_en' => 'Tunisia',
            ]);

        $response->assertCreated()
            ->assertJsonPath('code', 'TN');
        $this->assertDatabaseHas('pays', ['code' => 'TN']);
    }

    public function test_pays_store_validation(): void
    {
        $admin = $this->createAdminForRef('pays');

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/pays', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'label_fr', 'label_ar', 'label_en']);
    }

    public function test_pays_store_unique_code(): void
    {
        $admin = $this->createAdminForRef('pays');
        Pays::create(['code' => 'MA', 'label_fr' => 'Maroc', 'label_ar' => 'المغرب', 'label_en' => 'Morocco', 'classement' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/pays', [
                'code'     => 'MA',
                'label_fr' => 'Duplicate',
                'label_ar' => 'مكرر',
                'label_en' => 'Duplicate',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_pays_show(): void
    {
        $admin = $this->createAdminForRef('pays');
        $pays  = Pays::create(['code' => 'MA', 'label_fr' => 'Maroc', 'label_ar' => 'المغرب', 'label_en' => 'Morocco', 'classement' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson("/api/pays/{$pays->id}");

        $response->assertOk()
            ->assertJsonPath('code', 'MA');
    }

    public function test_pays_update(): void
    {
        $admin = $this->createAdminForRef('pays');
        $pays  = Pays::create(['code' => 'MA', 'label_fr' => 'Maroc', 'label_ar' => 'المغرب', 'label_en' => 'Morocco', 'classement' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/pays/{$pays->id}", [
                'code'     => 'MA',
                'label_fr' => 'Royaume du Maroc',
                'label_ar' => 'المملكة المغربية',
                'label_en' => 'Kingdom of Morocco',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('pays', ['id' => $pays->id, 'label_fr' => 'Royaume du Maroc']);
    }

    public function test_pays_destroy(): void
    {
        $admin = $this->createAdminForRef('pays');
        $pays  = Pays::create(['code' => 'XX', 'label_fr' => 'Test', 'label_ar' => 'اختبار', 'label_en' => 'Test', 'classement' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/pays/{$pays->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('pays', ['id' => $pays->id]);
    }

    public function test_pays_toggle_active(): void
    {
        $admin = $this->createAdminForRef('pays');
        $pays  = Pays::create(['code' => 'MA', 'label_fr' => 'Maroc', 'label_ar' => 'المغرب', 'label_en' => 'Morocco', 'is_active' => true, 'classement' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->patchJson("/api/pays/{$pays->id}/toggle-active");

        $response->assertOk();
        $this->assertFalse((bool) $response->json('is_active'));
    }

    public function test_pays_duplicate(): void
    {
        $admin = $this->createAdminForRef('pays');
        $pays  = Pays::create(['code' => 'MA', 'label_fr' => 'Maroc', 'label_ar' => 'المغرب', 'label_en' => 'Morocco', 'classement' => 0]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson("/api/pays/{$pays->id}/duplicate");

        $response->assertCreated();
        $this->assertStringContainsString('(copie)', $response->json('label_fr'));
        $this->assertEquals(2, Pays::count());
    }

    public function test_pays_reorder(): void
    {
        $admin = $this->createAdminForRef('pays');
        $p1 = Pays::create(['code' => 'MA', 'label_fr' => 'Maroc', 'label_ar' => 'المغرب', 'label_en' => 'Morocco', 'classement' => 0]);
        $p2 = Pays::create(['code' => 'FR', 'label_fr' => 'France', 'label_ar' => 'فرنسا', 'label_en' => 'France', 'classement' => 1]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/pays/reorder', [
                'items' => [
                    ['id' => $p1->id, 'classement' => 10],
                    ['id' => $p2->id, 'classement' => 0],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('pays', ['id' => $p1->id, 'classement' => 10]);
        $this->assertDatabaseHas('pays', ['id' => $p2->id, 'classement' => 0]);
    }

    public function test_pays_denied_without_permission(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pays')
            ->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════
    //  VILLES — CRUD + lien avec Pays
    // ══════════════════════════════════════════════════════════════

    public function test_villes_crud(): void
    {
        $admin = $this->createAdminForRef('villes');
        $pays  = Pays::create(['code' => 'MA', 'label_fr' => 'Maroc', 'label_ar' => 'المغرب', 'label_en' => 'Morocco', 'classement' => 0]);

        // Store
        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/villes', [
                'label_fr' => 'Casablanca',
                'label_ar' => 'الدار البيضاء',
                'label_en' => 'Casablanca',
                'pays_id'  => $pays->id,
            ]);
        $response->assertCreated();
        $villeId = $response->json('id');

        // Index
        $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/villes')
            ->assertOk();

        // Show
        $this->withHeaders($this->authHeader($admin))
            ->getJson("/api/villes/{$villeId}")
            ->assertOk()
            ->assertJsonPath('label_fr', 'Casablanca');

        // Update
        $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/villes/{$villeId}", [
                'label_fr' => 'Casa',
                'label_ar' => 'كازا',
                'label_en' => 'Casa',
                'pays_id'  => $pays->id,
            ])
            ->assertOk();
        $this->assertDatabaseHas('villes', ['id' => $villeId, 'label_fr' => 'Casa']);

        // Delete
        $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/villes/{$villeId}")
            ->assertOk();
        $this->assertDatabaseMissing('villes', ['id' => $villeId]);
    }

    // ══════════════════════════════════════════════════════════════
    //  FONCTIONS
    // ══════════════════════════════════════════════════════════════

    public function test_fonctions_crud(): void
    {
        $admin = $this->createAdminForRef('fonctions');

        // Store
        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/fonctions', [
                'code'     => 'AVO',
                'label_fr' => 'Avocat',
                'label_ar' => 'محامي',
            ]);
        $response->assertCreated();
        $id = $response->json('id');

        // Index
        $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/fonctions')
            ->assertOk();

        // Show
        $this->withHeaders($this->authHeader($admin))
            ->getJson("/api/fonctions/{$id}")
            ->assertOk();

        // Update
        $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/fonctions/{$id}", [
                'code'     => 'STA',
                'label_fr' => 'Stagiaire',
                'label_ar' => 'متدرب',
            ])
            ->assertOk();

        // Toggle active
        $this->withHeaders($this->authHeader($admin))
            ->patchJson("/api/fonctions/{$id}/toggle-active")
            ->assertOk();

        // Delete
        $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/fonctions/{$id}")
            ->assertOk();
    }

    // ══════════════════════════════════════════════════════════════
    //  GRADES
    // ══════════════════════════════════════════════════════════════

    public function test_grades_crud(): void
    {
        $admin = $this->createAdminForRef('grades');

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/grades', [
                'code'     => 'GA',
                'label_fr' => 'Grade A',
                'label_ar' => 'الدرجة أ',
            ]);
        $response->assertCreated();
        $id = $response->json('id');

        $this->withHeaders($this->authHeader($admin))->getJson('/api/grades')->assertOk();
        $this->withHeaders($this->authHeader($admin))->getJson("/api/grades/{$id}")->assertOk();
        $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/grades/{$id}", ['code' => 'GB', 'label_fr' => 'Grade B', 'label_ar' => 'الدرجة ب'])
            ->assertOk();
        $this->withHeaders($this->authHeader($admin))->deleteJson("/api/grades/{$id}")->assertOk();
    }

    // ══════════════════════════════════════════════════════════════
    //  DEPARTEMENTS
    // ══════════════════════════════════════════════════════════════

    public function test_departements_crud(): void
    {
        $admin = $this->createAdminForRef('departements');

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/departements', [
                'label_fr' => 'Contentieux',
                'label_ar' => 'المنازعات',
            ]);
        $response->assertCreated();
        $id = $response->json('id');

        $this->withHeaders($this->authHeader($admin))->getJson('/api/departements')->assertOk();
        $this->withHeaders($this->authHeader($admin))->getJson("/api/departements/{$id}")->assertOk();
        $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/departements/{$id}", ['label_fr' => 'Conseil', 'label_ar' => 'الاستشارة'])
            ->assertOk();
        $this->withHeaders($this->authHeader($admin))->deleteJson("/api/departements/{$id}")->assertOk();
    }

    // ══════════════════════════════════════════════════════════════
    //  GROUPES
    // ══════════════════════════════════════════════════════════════

    public function test_groupes_crud(): void
    {
        $admin = $this->createAdminForRef('groupes');

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/groupes', [
                'label_fr' => 'Groupe Alpha',
                'label_ar' => 'مجموعة ألفا',
            ]);
        $response->assertCreated();
        $id = $response->json('id');

        $this->withHeaders($this->authHeader($admin))->getJson('/api/groupes')->assertOk();
        $this->withHeaders($this->authHeader($admin))->getJson("/api/groupes/{$id}")->assertOk();
        $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/groupes/{$id}", ['label_fr' => 'Groupe Beta', 'label_ar' => 'مجموعة بيتا'])
            ->assertOk();
        $this->withHeaders($this->authHeader($admin))->deleteJson("/api/groupes/{$id}")->assertOk();
    }
}
