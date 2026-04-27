<?php

namespace Tests\Unit\Actions\Schools;

use App\Actions\Schools\ProvisionSchoolAction;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ProvisionSchoolActionFuzzTest extends TestCase
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
            'name' => 'Escola Fuzz',
            'slug' => 'escola-fuzz',
            'email' => 'contato@escolafuzz.com',
            'admin_name' => 'Admin Fuzz',
            'admin_email' => 'admin@escolafuzz.com',
            'admin_password' => 'secret-fuzz-123',
        ], $overrides);
    }

    // ── Empty / blank name ────────────────────────────────────────

    public function test_provision_with_empty_name_throws_or_fails(): void
    {
        // The action does not validate inputs -- it relies on DB constraints.
        // An empty string for 'name' is accepted by SQLite's string column (no NOT NULL CHECK length).
        // We verify the behavior is deterministic: either it throws or creates with empty name.
        $exceptionThrown = false;

        try {
            $result = $this->action->execute($this->validData(['name' => '']));
        } catch (\Throwable $e) {
            $exceptionThrown = true;
        }

        if ($exceptionThrown) {
            // If an exception was thrown, no school should exist with empty name
            $this->assertSame(0, School::where('name', '')->count());
        } else {
            // If it succeeded, the school was created with empty name (no DB-level check)
            $this->assertSame('', $result['school']->name);
            $this->assertNotNull($result['school']->id);
        }
    }

    // ── Duplicate slug rollback ───────────────────────────────────

    public function test_provision_with_duplicate_slug_throws_and_rolls_back(): void
    {
        // Create a school with a known slug (migration already seeds 'tkl', so use a fresh one)
        School::factory()->create(['slug' => 'dup-slug-test']);

        $schoolCountBefore = School::count();
        $userCountBefore = User::count();

        try {
            $this->action->execute($this->validData(['slug' => 'dup-slug-test']));
            $this->fail('Expected exception for duplicate slug was not thrown.');
        } catch (\Throwable) {
            // Expected: unique constraint violation
        }

        // Transaction must have rolled back -- no new school or admin
        $this->assertSame($schoolCountBefore, School::count());
        $this->assertSame($userCountBefore, User::count());
    }

    // ── Duplicate admin email rollback ────────────────────────────

    public function test_provision_with_duplicate_admin_email_rolls_back_school(): void
    {
        // Pre-create a user with the target email
        User::factory()->create(['email' => 'taken@example.com']);

        $schoolCountBefore = School::count();

        try {
            $this->action->execute($this->validData([
                'slug' => 'unique-slug-for-email-test',
                'admin_email' => 'taken@example.com',
            ]));
            $this->fail('Expected exception for duplicate admin email was not thrown.');
        } catch (\Throwable) {
            // Expected
        }

        // School must NOT have been created (transaction rolled back)
        $this->assertSame($schoolCountBefore, School::count());
        $this->assertSame(0, School::where('slug', 'unique-slug-for-email-test')->count());
    }

    // ── Very long admin name ──────────────────────────────────────

    public function test_provision_with_very_long_admin_name_truncates_or_throws(): void
    {
        $longName = str_repeat('A', 500);

        $exceptionThrown = false;

        try {
            $result = $this->action->execute($this->validData(['admin_name' => $longName]));
        } catch (\Throwable $e) {
            $exceptionThrown = true;
        }

        if ($exceptionThrown) {
            // If DB rejects long name, no school should exist (rollback)
            $this->assertSame(0, School::where('slug', 'escola-fuzz')->count());
        } else {
            // SQLite does not enforce varchar length -- name is stored fully
            $this->assertNotNull($result['admin']->id);
            $this->assertIsString($result['admin']->name);
            // Verify no silent corruption: name stored matches what was given
            $this->assertSame($longName, $result['admin']->fresh()->name);
        }
    }

    // ── Admin school_id always matches provisioned school ─────────

    public static function randomSchoolDataProvider(): array
    {
        return [
            'combo-1' => [['name' => 'Alpha School', 'slug' => 'alpha-school']],
            'combo-2' => [['name' => 'Beta Academy', 'slug' => 'beta-academy']],
            'combo-3' => [['name' => 'Gamma Institute', 'slug' => 'gamma-inst']],
            'combo-4' => [['name' => 'Delta Center', 'slug' => 'delta-center']],
            'combo-5' => [['name' => 'Epsilon Hub', 'slug' => 'epsilon-hub']],
        ];
    }

    #[DataProvider('randomSchoolDataProvider')]
    public function test_provision_school_id_on_admin_matches_school(array $combo): void
    {
        $data = $this->validData(array_merge($combo, [
            'admin_email' => 'admin-'.$combo['slug'].'@test.com',
        ]));

        $result = $this->action->execute($data);

        $this->assertSame($result['school']->id, $result['admin']->school_id);
        $this->assertSame($result['school']->id, $result['admin']->fresh()->school_id);
    }

    // ── Password not leaked in logs ───────────────────────────────

    public function test_log_does_not_contain_password(): void
    {
        // Wave 8 / Fix M4: provisioning now writes to the dedicated `audit`
        // channel via App\Support\Audit. We spy on that channel specifically
        // so this regression test continues to assert the original property
        // (the plaintext admin password must never appear anywhere in logs)
        // against the new audit emission path.
        $auditSpy = \Mockery::spy(LoggerInterface::class);
        Log::shouldReceive('channel')->with('audit')->andReturn($auditSpy);

        $data = $this->validData(['admin_password' => 'super-secret-p@ssw0rd!']);

        $this->action->execute($data);

        $auditSpy->shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($data) {
            // No password-shaped keys in the context.
            foreach (['admin_password', 'password', 'admin_password_confirmation'] as $key) {
                if (isset($context[$key])) {
                    return false;
                }
            }
            // The serialized context must NOT contain the plain-text password.
            $serialized = json_encode($context);
            if ($serialized !== false && str_contains($serialized, $data['admin_password'])) {
                return false;
            }

            return true;
        });
    }

    // ── Adversarial slug inputs ───────────────────────────────────

    public static function invalidSlugCases(): array
    {
        return [
            'empty string' => [''],
            'spaces only' => ['   '],
            'sql injection' => ["'; DROP TABLE schools; --"],
            'xss attempt' => ['<script>alert(1)</script>'],
            'very long slug' => [str_repeat('a', 300)],
            'unicode chars' => ['acao-escola'],
            'uppercase' => ['TKL-SCHOOL'],
        ];
    }

    #[DataProvider('invalidSlugCases')]
    public function test_adversarial_slug_does_not_corrupt_database(string $slug): void
    {
        $data = $this->validData([
            'slug' => $slug,
            'admin_email' => 'admin-slug-'.md5($slug).'@test.com',
        ]);

        $exceptionThrown = false;

        try {
            $result = $this->action->execute($data);
        } catch (\Throwable) {
            $exceptionThrown = true;
        }

        if (! $exceptionThrown) {
            // If it succeeded, verify data integrity
            $school = School::where('slug', $slug)->first();
            $this->assertNotNull($school, "School with slug '{$slug}' should exist in DB.");
            $this->assertSame($slug, $school->slug);
            // Verify the schools table is intact (not dropped by injection)
            $this->assertTrue(School::count() >= 1);
        } else {
            // If it threw, verify rollback: no partial data
            $this->assertSame(0, School::where('slug', $slug)->count());
        }
    }
}
