<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for the white-label identity feature on schools.
 *
 * Covers:
 *  - Logo upload during school provisioning (super_admin route).
 *  - Color (hex) validation on both store and update endpoints.
 *  - Logo replacement on update (old file is deleted).
 *  - Logo removal via the `remove_logo` flag.
 *  - HandleInertiaRequests exposing the auth.school.theme + logo_url payload.
 */
class SchoolWhiteLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    private function superAdmin(): User
    {
        $user = new User;
        $user->name = 'Super Admin';
        $user->email = 'super@white-label-test.com';
        $user->password = bcrypt('password');
        $user->role = 'super_admin';
        $user->school_id = null;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function schoolAdmin(School $school): User
    {
        $user = new User;
        $user->name = 'School Admin';
        $user->email = 'schooladmin@white-label-test.com';
        $user->password = bcrypt('password');
        $user->role = 'school_admin';
        $user->school_id = $school->id;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function provisionPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Escola White Label',
            'slug' => 'escola-white-label',
            'email' => 'contato@whitelabel.com',
            'admin_name' => 'Admin White Label',
            'admin_email' => 'admin@whitelabel.com',
            // Must satisfy Password::defaults() strict rule:
            // 12+ chars, mixed case, number, symbol.
            'admin_password' => 'StrongPass!2026',
            'admin_password_confirmation' => 'StrongPass!2026',
        ], $overrides);
    }

    // ── Provisioning with logo upload ─────────────────────────────

    public function test_super_admin_can_provision_school_with_logo_upload(): void
    {
        Storage::fake('public');

        $superAdmin = $this->superAdmin();
        $logo = UploadedFile::fake()->image('brand.png', 200, 200);

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->provisionPayload([
                'logo' => $logo,
            ]));

        $response->assertRedirect(route('platform.schools.index'));
        $response->assertSessionHasNoErrors();

        $school = School::where('slug', 'escola-white-label')->firstOrFail();
        $this->assertNotNull($school->logo_url, 'logo_url should be persisted');
        Storage::disk('public')->assertExists($school->logo_url);
    }

    public function test_provision_with_custom_colors_persists_them(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->provisionPayload([
                'primary_color' => '#ff8800',
                'secondary_color' => '#112233',
            ]))
            ->assertRedirect();

        $school = School::where('slug', 'escola-white-label')->firstOrFail();
        $this->assertSame('#ff8800', $school->primary_color);
        $this->assertSame('#112233', $school->secondary_color);
    }

    public function test_provision_without_colors_uses_database_defaults(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->provisionPayload())
            ->assertRedirect();

        $school = School::where('slug', 'escola-white-label')->firstOrFail();
        $this->assertSame('#4f46e5', $school->primary_color);
        $this->assertSame('#0f172a', $school->secondary_color);
    }

    // ── Color validation ──────────────────────────────────────────

    public function test_store_rejects_named_color_strings(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->provisionPayload([
                'primary_color' => 'red',
            ]))
            ->assertSessionHasErrors('primary_color');
    }

    public function test_store_rejects_hex_with_invalid_characters(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->provisionPayload([
                'secondary_color' => '#zzzzzz',
            ]))
            ->assertSessionHasErrors('secondary_color');
    }

    public function test_store_rejects_short_hex(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->provisionPayload([
                'primary_color' => '#abc',
            ]))
            ->assertSessionHasErrors('primary_color');
    }

    public function test_store_rejects_hex_without_hash_prefix(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->provisionPayload([
                'primary_color' => '4f46e5',
            ]))
            ->assertSessionHasErrors('primary_color');
    }

    // ── Logo file validation ──────────────────────────────────────

    public function test_store_rejects_logo_larger_than_2mb(): void
    {
        Storage::fake('public');

        $superAdmin = $this->superAdmin();
        // 2049 KB = 1 KB above the 2048 limit.
        $logo = UploadedFile::fake()->image('huge.png')->size(2049);

        $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->provisionPayload([
                'logo' => $logo,
            ]))
            ->assertSessionHasErrors('logo');
    }

    public function test_store_rejects_non_image_logo(): void
    {
        Storage::fake('public');

        $superAdmin = $this->superAdmin();
        $logo = UploadedFile::fake()->create('not-an-image.pdf', 100, 'application/pdf');

        $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->provisionPayload([
                'logo' => $logo,
            ]))
            ->assertSessionHasErrors('logo');
    }

    public function test_store_rejects_svg_logo_upload(): void
    {
        // SVG can embed <script>, which would execute under the app origin and
        // turn a logo upload into stored XSS. The mimes:png,jpg,jpeg,webp rule
        // must reject SVG outright.
        Storage::fake('public');

        $superAdmin = $this->superAdmin();

        // Realistic-looking SVG payload — content is irrelevant for the mimes
        // rule (which inspects the extension/MIME); we just need a real .svg file.
        $svgBytes = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';
        $logo = UploadedFile::fake()->createWithContent('logo.svg', $svgBytes);

        $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->provisionPayload([
                'logo' => $logo,
            ]))
            ->assertSessionHasErrors('logo');
    }

    // ── Update: logo replacement ──────────────────────────────────

    public function test_update_replaces_existing_logo_and_deletes_old_file(): void
    {
        Storage::fake('public');

        $school = School::factory()->create([
            'logo_url' => 'schools/logos/old-logo.png',
        ]);
        // Materialise the "old" file on the fake disk so the deletion has something to remove.
        Storage::disk('public')->put('schools/logos/old-logo.png', 'fake-old-bytes');

        $superAdmin = $this->superAdmin();
        $newLogo = UploadedFile::fake()->image('new-brand.png');

        $this->actingAs($superAdmin)
            ->put(route('platform.schools.update', $school), [
                'name' => $school->name,
                'slug' => $school->slug,
                'email' => $school->email,
                'active' => true,
                'logo' => $newLogo,
            ])
            ->assertRedirect();

        $school->refresh();

        $this->assertNotSame('schools/logos/old-logo.png', $school->logo_url);
        $this->assertNotNull($school->logo_url);
        Storage::disk('public')->assertExists($school->logo_url);
        Storage::disk('public')->assertMissing('schools/logos/old-logo.png');
    }

    public function test_update_can_remove_existing_logo_via_remove_logo_flag(): void
    {
        Storage::fake('public');

        $school = School::factory()->create([
            'logo_url' => 'schools/logos/keep-me.png',
        ]);
        Storage::disk('public')->put('schools/logos/keep-me.png', 'fake-bytes');

        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->put(route('platform.schools.update', $school), [
                'name' => $school->name,
                'slug' => $school->slug,
                'email' => $school->email,
                'active' => true,
                'remove_logo' => true,
            ])
            ->assertRedirect();

        $school->refresh();

        $this->assertNull($school->logo_url);
        Storage::disk('public')->assertMissing('schools/logos/keep-me.png');
    }

    public function test_update_persists_color_changes(): void
    {
        $school = School::factory()->create([
            'primary_color' => '#4f46e5',
            'secondary_color' => '#0f172a',
        ]);
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->put(route('platform.schools.update', $school), [
                'name' => $school->name,
                'slug' => $school->slug,
                'email' => $school->email,
                'active' => true,
                'primary_color' => '#dd2222',
                'secondary_color' => '#222266',
            ])
            ->assertRedirect();

        $school->refresh();
        $this->assertSame('#dd2222', $school->primary_color);
        $this->assertSame('#222266', $school->secondary_color);
    }

    public function test_update_rejects_invalid_color_format(): void
    {
        $school = School::factory()->create();
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->put(route('platform.schools.update', $school), [
                'name' => $school->name,
                'slug' => $school->slug,
                'email' => $school->email,
                'active' => true,
                'primary_color' => 'not-a-color',
            ])
            ->assertSessionHasErrors('primary_color');
    }

    // ── Inertia shared theme payload ──────────────────────────────

    public function test_inertia_share_exposes_school_theme_and_logo_url(): void
    {
        Storage::fake('public');

        $school = School::factory()->create([
            'name' => 'Tenant Theme School',
            'slug' => 'tenant-theme-school',
            'logo_url' => 'schools/logos/test-logo.png',
            'primary_color' => '#abcdef',
            'secondary_color' => '#123456',
        ]);
        Storage::disk('public')->put('schools/logos/test-logo.png', 'fake-bytes');

        $admin = $this->schoolAdmin($school);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('auth.school.id', $school->id)
            ->where('auth.school.name', 'Tenant Theme School')
            ->where('auth.school.theme.primary', '#abcdef')
            ->where('auth.school.theme.secondary', '#123456')
            ->where('auth.school.logo_url', fn ($url) => is_string($url) && str_contains($url, 'test-logo.png'))
        );
    }

    public function test_inertia_share_returns_null_school_for_super_admin(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->get('/platform/dashboard');

        $response->assertInertia(fn ($page) => $page->where('auth.school', null));
    }
}
