<?php

namespace Tests\Feature;

use App\Models\CabinetConfig;
use App\Models\ColorTheme;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CabinetConfigIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────

    private function createAdmin(): User
    {
        $perm = Permission::firstOrCreate(
            ['name' => 'admin.cabinet'],
            ['module' => 'admin', 'action' => 'cabinet']
        );
        $settingsPerm = Permission::firstOrCreate(
            ['name' => 'admin.settings'],
            ['module' => 'admin', 'action' => 'settings']
        );
        $role = Role::firstOrCreate(
            ['name' => 'superadmin'],
            ['level' => 200, 'color' => '#ef4444']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id, $settingsPerm->id]);

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

    private function seedDefaultTheme(): ColorTheme
    {
        return ColorTheme::create([
            'slug'       => 'green',
            'label'      => 'Vert',
            'color1'     => '#10b981',
            'color2'     => '#059669',
            'color3'     => '#047857',
            'is_default' => true,
            'classement' => 0,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  CONFIG GET / UPDATE
    // ══════════════════════════════════════════════════════════════

    public function test_get_config_returns_singleton(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/cabinet/config');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'firm_name_fr']]);
    }

    public function test_update_config(): void
    {
        $admin = $this->createAdmin();
        $this->seedDefaultTheme();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/cabinet/config', [
                'firm_name_fr' => 'Cabinet Test',
                'firm_email'   => 'test@cabinet.ma',
                'color_theme'  => 'green',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('cabinet_configs', ['firm_name_fr' => 'Cabinet Test']);
    }

    public function test_update_config_validation(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/cabinet/config', [
                'firm_email'  => 'not-an-email',
                'color_theme' => 'nonexistent-theme',
            ]);

        $response->assertUnprocessable();
    }

    // ══════════════════════════════════════════════════════════════
    //  COLOR THEMES
    // ══════════════════════════════════════════════════════════════

    public function test_get_themes(): void
    {
        $admin = $this->createAdmin();
        $this->seedDefaultTheme();

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/cabinet/themes');

        $response->assertOk()
            ->assertJsonStructure(['data']);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_store_theme(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/cabinet/themes', [
                'label'  => 'Violet Moderne',
                'color1' => '#6D28D9',
                'color2' => '#A855F7',
                'color3' => '#F472B6',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('color_themes', ['slug' => 'violet-moderne']);
    }

    public function test_store_theme_auto_slug_uniqueness(): void
    {
        $admin = $this->createAdmin();
        ColorTheme::create([
            'slug'       => 'custom',
            'label'      => 'Custom',
            'color1'     => '#000000',
            'color2'     => '#111111',
            'color3'     => '#222222',
            'classement' => 0,
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/cabinet/themes', [
                'label'  => 'Custom',
                'color1' => '#333333',
                'color2' => '#444444',
                'color3' => '#555555',
            ]);

        $response->assertCreated();
        // Slug should be 'custom-1' since 'custom' already exists
        $this->assertDatabaseHas('color_themes', ['slug' => 'custom-1']);
    }

    public function test_store_theme_hex_validation(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/cabinet/themes', [
                'label'  => 'Bad',
                'color1' => 'notahex',
                'color2' => '#GGG',
                'color3' => '123456',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['color1', 'color2', 'color3']);
    }

    public function test_update_theme(): void
    {
        $admin = $this->createAdmin();
        $theme = ColorTheme::create([
            'slug'       => 'test-theme',
            'label'      => 'Test',
            'color1'     => '#000000',
            'color2'     => '#111111',
            'color3'     => '#222222',
            'classement' => 0,
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->putJson("/api/cabinet/themes/{$theme->id}", [
                'label'  => 'Updated Theme',
                'color1' => '#FF0000',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('color_themes', ['id' => $theme->id, 'color1' => '#FF0000']);
    }

    public function test_destroy_theme(): void
    {
        $admin = $this->createAdmin();
        $theme = ColorTheme::create([
            'slug'       => 'deletable',
            'label'      => 'Deletable',
            'color1'     => '#000000',
            'color2'     => '#111111',
            'color3'     => '#222222',
            'is_default' => false,
            'classement' => 0,
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/cabinet/themes/{$theme->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('color_themes', ['id' => $theme->id]);
    }

    public function test_destroy_default_theme_forbidden(): void
    {
        $admin = $this->createAdmin();
        $theme = $this->seedDefaultTheme();

        $response = $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/cabinet/themes/{$theme->id}");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Impossible de supprimer un thème par défaut']);
    }

    public function test_destroy_theme_resets_cabinet_config(): void
    {
        $admin   = $this->createAdmin();
        $default = $this->seedDefaultTheme();
        $custom  = ColorTheme::create([
            'slug'       => 'custom-temp',
            'label'      => 'Temp',
            'color1'     => '#000000',
            'color2'     => '#111111',
            'color3'     => '#222222',
            'is_default' => false,
            'classement' => 1,
        ]);

        // Set cabinet config to use the custom theme
        $config = CabinetConfig::getConfig();
        $config->update(['color_theme' => 'custom-temp']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->deleteJson("/api/cabinet/themes/{$custom->id}");

        $response->assertOk();
        $config->refresh();
        $this->assertEquals('green', $config->color_theme);
    }

    // ══════════════════════════════════════════════════════════════
    //  IMAGE UPLOAD / DELETE
    // ══════════════════════════════════════════════════════════════

    public function test_upload_image(): void
    {
        Storage::fake('public');
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/cabinet/upload-image', [
                'file' => UploadedFile::fake()->image('logo.png', 200, 200),
                'type' => 'logo',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['url']]);
    }

    public function test_upload_image_type_validation(): void
    {
        Storage::fake('public');
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/cabinet/upload-image', [
                'file' => UploadedFile::fake()->image('logo.png'),
                'type' => 'invalid',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_delete_image(): void
    {
        Storage::fake('public');
        $admin  = $this->createAdmin();
        $config = CabinetConfig::getConfig();
        $config->update(['firm_logo_url' => '/storage/cabinet/logo/test.png']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->deleteJson('/api/cabinet/delete-image', ['type' => 'logo']);

        $response->assertOk();
        $config->refresh();
        $this->assertNull($config->firm_logo_url);
    }

    // ══════════════════════════════════════════════════════════════
    //  ACCESS CONTROL
    // ══════════════════════════════════════════════════════════════

    public function test_unpermissioned_user_cannot_manage_cabinet(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/cabinet/config')
            ->assertForbidden();
    }
}
