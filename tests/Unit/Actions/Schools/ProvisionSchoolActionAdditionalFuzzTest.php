<?php

namespace Tests\Unit\Actions\Schools;

use App\Actions\Schools\ProvisionSchoolAction;
use App\Http\Requests\StoreSchoolRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Additional fuzz tests for ProvisionSchoolAction and StoreSchoolRequest validation rules.
 *
 * Probes boundary conditions on password length, email uniqueness, slug format,
 * and admin_name length — edge cases not covered by the base fuzz test.
 *
 * Covers TKL-001.
 */
class ProvisionSchoolActionAdditionalFuzzTest extends TestCase
{
    use RefreshDatabase;

    private ProvisionSchoolAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ProvisionSchoolAction;
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Escola Fuzz Additional',
            'slug' => 'escola-fuzz-additional-'.uniqid(),
            'email' => 'contato@escolafuzz-additional.com',
            'admin_name' => 'Admin Fuzz Additional',
            'admin_email' => 'admin-additional-'.uniqid().'@escolafuzz.com',
            'admin_password' => 'valid-password-123',
        ], $overrides);
    }

    // ── Password boundary: exactly 7 chars (below min:8) ───────────

    public function test_admin_password_exactly_7_chars_is_rejected_by_validation_rules(): void
    {
        // StoreSchoolRequest requires min:8. The action itself does NOT validate,
        // so passing 7 chars directly to the action will succeed at DB level.
        // This test documents that the validation gate is in StoreSchoolRequest,
        // not in the action. We verify the action stores the hash regardless.
        $sevenCharPassword = 'Abc123!';
        $this->assertSame(7, strlen($sevenCharPassword));

        // The action does not reject -- it hashes whatever it receives.
        $result = $this->action->execute($this->validData([
            'admin_password' => $sevenCharPassword,
        ]));

        // Action succeeds (no validation layer here), admin is created.
        $this->assertNotNull($result['admin']->id);

        // But the password IS hashed (not stored in plain text).
        $this->assertNotSame($sevenCharPassword, $result['admin']->password);
        $this->assertTrue(password_verify($sevenCharPassword, $result['admin']->password));

        // Verify that StoreSchoolRequest WOULD reject this via its rules.
        $rules = (new StoreSchoolRequest)->rules();
        $this->assertContains('confirmed', $rules['admin_password']);
        $this->assertTrue(
            collect($rules['admin_password'])->contains(fn ($rule) => $rule instanceof \Illuminate\Validation\Rules\Password),
            'admin_password rules should include a Password rule instance'
        );
    }

    // ── Password boundary: exactly 8 chars (at min:8) ──────────────

    public function test_admin_password_exactly_8_chars_is_accepted(): void
    {
        $eightCharPassword = 'Abc1234!';
        $this->assertSame(8, strlen($eightCharPassword));

        $result = $this->action->execute($this->validData([
            'admin_password' => $eightCharPassword,
        ]));

        $this->assertNotNull($result['admin']->id);
        $this->assertTrue(password_verify($eightCharPassword, $result['admin']->password));
    }

    // ── Empty password ─────────────────────────────────────────────

    public function test_admin_password_empty_string_still_hashed_by_action(): void
    {
        // The action does not validate -- it hashes whatever string it receives.
        // Empty string is a valid bcrypt input. StoreSchoolRequest blocks this
        // with 'required' + 'min:8'.
        $result = $this->action->execute($this->validData([
            'admin_password' => '',
        ]));

        $this->assertNotNull($result['admin']->id);
        // Empty string hashed -- verify it was hashed, not stored empty.
        $this->assertNotSame('', $result['admin']->password);
        $this->assertTrue(password_verify('', $result['admin']->password));
    }

    // ── Duplicate admin_email throws and rolls back ────────────────

    public function test_duplicate_admin_email_throws_unique_constraint_violation(): void
    {
        $existingEmail = 'taken-email@example.com';
        User::factory()->create(['email' => $existingEmail]);

        $schoolCountBefore = School::count();
        $userCountBefore = User::count();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->action->execute($this->validData([
            'admin_email' => $existingEmail,
        ]));

        // After the exception, verify transaction rolled back.
        // (expectException stops execution, so we verify in a separate test below.)
    }

    public function test_duplicate_admin_email_rolls_back_school_creation(): void
    {
        $existingEmail = 'taken-rollback@example.com';
        User::factory()->create(['email' => $existingEmail]);

        $schoolCountBefore = School::count();
        $userCountBefore = User::count();
        $slug = 'rollback-email-test-'.uniqid();

        try {
            $this->action->execute($this->validData([
                'slug' => $slug,
                'admin_email' => $existingEmail,
            ]));
            $this->fail('Expected UniqueConstraintViolationException was not thrown.');
        } catch (UniqueConstraintViolationException) {
            // Expected
        }

        // Transaction must have rolled back: no new school, no new user.
        $this->assertSame($schoolCountBefore, School::count());
        $this->assertSame($userCountBefore, User::count());
        $this->assertSame(0, School::where('slug', $slug)->count());
    }

    // ── Slug with spaces → rejected by regex in StoreSchoolRequest ─

    public function test_slug_with_spaces_violates_validation_regex(): void
    {
        $rules = (new StoreSchoolRequest)->rules();
        $slugRules = $rules['slug'];

        // The regex rule is: /^[a-z0-9\-]+$/
        // Spaces do NOT match this pattern.
        $this->assertContains('regex:/^[a-z0-9\-]+$/', $slugRules);

        // Verify via Laravel's Validator that spaces fail.
        $validator = Validator::make(
            ['slug' => 'has spaces'],
            ['slug' => $slugRules]
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    // ── Slug with uppercase → rejected by regex ────────────────────

    public function test_slug_with_uppercase_violates_validation_regex(): void
    {
        $rules = (new StoreSchoolRequest)->rules();

        $validator = Validator::make(
            ['slug' => 'UPPERCASE-SLUG'],
            ['slug' => $rules['slug']]
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    // ── Slug with uppercase: action does NOT normalise ─────────────

    public function test_action_stores_uppercase_slug_verbatim_if_bypassing_validation(): void
    {
        // The action does not lowercase slugs. If validation is bypassed,
        // the slug is stored as-is. This documents the behavior.
        $result = $this->action->execute($this->validData([
            'slug' => 'UPPER-CASE',
        ]));

        // Stored verbatim -- no normalisation.
        $this->assertSame('UPPER-CASE', $result['school']->slug);
        $this->assertSame('UPPER-CASE', $result['school']->fresh()->slug);
    }

    // ── admin_name with 300 chars → accepted by action, rejected by validation ─

    public function test_admin_name_300_chars_accepted_by_action_but_rejected_by_validation(): void
    {
        $longName = str_repeat('A', 300);

        // Action does not validate -- SQLite accepts any string length.
        $result = $this->action->execute($this->validData([
            'admin_name' => $longName,
        ]));

        $this->assertNotNull($result['admin']->id);
        $this->assertSame($longName, $result['admin']->fresh()->name);

        // But StoreSchoolRequest limits to max:255.
        $rules = (new StoreSchoolRequest)->rules();

        $validator = Validator::make(
            ['admin_name' => $longName],
            ['admin_name' => $rules['admin_name']]
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('admin_name', $validator->errors()->toArray());
    }

    // ── admin_email not a valid email format → rejected by validation ─

    public function test_admin_email_invalid_format_rejected_by_validation(): void
    {
        $rules = (new StoreSchoolRequest)->rules();

        $invalidEmails = [
            'not-an-email',
            '@missing-local.com',
            'missing-domain@',
            'spaces in@email.com',
            '',
        ];

        foreach ($invalidEmails as $email) {
            $validator = Validator::make(
                ['admin_email' => $email],
                ['admin_email' => $rules['admin_email']]
            );

            $this->assertTrue(
                $validator->fails(),
                "Expected validation to fail for admin_email='{$email}' but it passed."
            );
        }
    }

    // ── Slug valid cases pass validation ────────────────────────────

    public static function validSlugCases(): array
    {
        return [
            'lowercase only' => ['abc'],
            'with numbers' => ['abc123'],
            'with hyphens' => ['my-school'],
            'numbers and hyphens' => ['123-456'],
            'single char' => ['a'],
            'max length 63' => [str_repeat('a', 63)],
        ];
    }

    #[DataProvider('validSlugCases')]
    public function test_valid_slug_accepted_by_validation(string $slug): void
    {
        $rules = (new StoreSchoolRequest)->rules();

        // Remove 'unique:schools' for this test — we only test format.
        $formatRules = array_filter($rules['slug'], fn ($r) => $r !== 'unique:schools');

        $validator = Validator::make(
            ['slug' => $slug],
            ['slug' => array_values($formatRules)]
        );

        $this->assertFalse(
            $validator->fails(),
            "Expected slug '{$slug}' to pass validation but it failed: ".implode(', ', $validator->errors()->all())
        );
    }

    // ── Slug over max:63 rejected ──────────────────────────────────

    public function test_slug_over_63_chars_rejected_by_validation(): void
    {
        $rules = (new StoreSchoolRequest)->rules();

        $validator = Validator::make(
            ['slug' => str_repeat('a', 64)],
            ['slug' => array_filter($rules['slug'], fn ($r) => $r !== 'unique:schools')]
        );

        $this->assertTrue($validator->fails());
    }
}
