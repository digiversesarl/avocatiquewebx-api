<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationIntegrationTest extends TestCase
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
    //  PUBLIC INDEX (i18n load)
    // ══════════════════════════════════════════════════════════════

    public function test_public_index_returns_translations(): void
    {
        Translation::create([
            'code'       => 'app.title',
            'libelle_fr' => 'Avocatique',
            'libelle_ar' => 'أفوكاتيك',
            'libelle_en' => 'Avocatique',
        ]);

        $response = $this->getJson('/api/translations');

        $response->assertOk();
        $this->assertNotEmpty($response->json());
    }

    public function test_public_index_no_auth_required(): void
    {
        $this->getJson('/api/translations')->assertOk();
    }

    // ══════════════════════════════════════════════════════════════
    //  PAGINATED (admin)
    // ══════════════════════════════════════════════════════════════

    public function test_paginated_returns_paginated_list(): void
    {
        $admin = $this->createAdmin();
        Translation::create([
            'code'       => 'menu.home',
            'libelle_fr' => 'Accueil',
            'libelle_ar' => 'الرئيسية',
            'libelle_en' => 'Home',
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/translations/paginated');

        $response->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_paginated_search_filter(): void
    {
        $admin = $this->createAdmin();
        Translation::create([
            'code'       => 'btn.save',
            'libelle_fr' => 'Enregistrer',
            'libelle_ar' => 'حفظ',
            'libelle_en' => 'Save',
        ]);
        Translation::create([
            'code'       => 'btn.cancel',
            'libelle_fr' => 'Annuler',
            'libelle_ar' => 'إلغاء',
            'libelle_en' => 'Cancel',
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/translations/paginated?search=Enregistrer');

        $response->assertOk();
        $items = collect($response->json('data'));
        $this->assertTrue($items->contains('code', 'btn.save'));
    }

    public function test_paginated_requires_auth(): void
    {
        $this->getJson('/api/translations/paginated')->assertUnauthorized();
    }

    // ══════════════════════════════════════════════════════════════
    //  STORE
    // ══════════════════════════════════════════════════════════════

    public function test_store_translation(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/translations-crud', [
                'code'       => 'new.key',
                'libelle_fr' => 'Nouvelle clé',
                'libelle_ar' => 'مفتاح جديد',
                'libelle_en' => 'New key',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('translations', ['code' => 'new.key']);
    }

    public function test_store_unique_code_validation(): void
    {
        $admin = $this->createAdmin();
        Translation::create([
            'code'       => 'dup.key',
            'libelle_fr' => 'Original',
            'libelle_ar' => 'أصلي',
            'libelle_en' => 'Original',
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/translations-crud', [
                'code'       => 'dup.key',
                'libelle_fr' => 'Copie',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    // ══════════════════════════════════════════════════════════════
    //  SHOW
    // ══════════════════════════════════════════════════════════════

    public function test_show_translation(): void
    {
        $admin = $this->createAdmin();
        $trans = Translation::create([
            'code'       => 'show.test',
            'libelle_fr' => 'Test',
            'libelle_ar' => 'اختبار',
            'libelle_en' => 'Test',
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson("/api/translations-crud/{$trans->id}");

        $response->assertOk()
            ->assertJsonPath('code', 'show.test');
    }

    // ══════════════════════════════════════════════════════════════
    //  UPDATE
    // ══════════════════════════════════════════════════════════════

    public function test_update_translation(): void
    {
        $admin = $this->createAdmin();
        $trans = Translation::create([
            'code'       => 'upd.key',
            'libelle_fr' => 'Ancien',
            'libelle_ar' => 'قديم',
            'libelle_en' => 'Old',
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/translations-crud/{$trans->id}", [
                'code'       => 'upd.key',
                'libelle_fr' => 'Nouveau',
                'libelle_ar' => 'جديد',
                'libelle_en' => 'New',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('translations', ['id' => $trans->id, 'libelle_fr' => 'Nouveau']);
    }

    // ══════════════════════════════════════════════════════════════
    //  DESTROY
    // ══════════════════════════════════════════════════════════════

    public function test_destroy_translation(): void
    {
        $admin = $this->createAdmin();
        $trans = Translation::create([
            'code'       => 'del.key',
            'libelle_fr' => 'À supprimer',
            'libelle_ar' => 'للحذف',
            'libelle_en' => 'To delete',
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/translations-crud/{$trans->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('translations', ['id' => $trans->id]);
    }

    // ══════════════════════════════════════════════════════════════
    //  ACCESS CONTROL
    // ══════════════════════════════════════════════════════════════

    public function test_unpermissioned_user_cannot_crud_translations(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/translations-crud', ['code' => 'x', 'libelle_fr' => 'y'])
            ->assertForbidden();
    }
}
