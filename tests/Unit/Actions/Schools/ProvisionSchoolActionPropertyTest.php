<?php

namespace Tests\Unit\Actions\Schools;

use App\Actions\Schools\ProvisionSchoolAction;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProvisionSchoolActionPropertyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * Generate a valid provisioning payload with random values.
     */
    private function randomPayload(?string $adminEmail = null, ?string $slug = null, ?string $password = null): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => $slug ?? Str::slug($name).'-'.Str::random(6),
            'email' => fake()->unique()->safeEmail(),
            'admin_name' => fake()->name(),
            'admin_email' => $adminEmail ?? fake()->unique()->safeEmail(),
            'admin_password' => $password ?? Str::random(rand(8, 32)),
        ];
    }

    // ── Invariant 1: Atomicity — failure rolls back both records ──

    public function test_property_provision_atomicity_no_school_on_duplicate_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            // Arrange: pre-create a user with a known email
            $takenEmail = fake()->unique()->safeEmail();
            User::factory()->create(['email' => $takenEmail]);

            $schoolCountBefore = School::count();
            $userCountBefore = User::count();

            // Act: attempt provisioning with the taken email
            $payload = $this->randomPayload(adminEmail: $takenEmail);

            try {
                (new ProvisionSchoolAction)->execute($payload);
                $this->fail("Expected UniqueConstraintViolationException for duplicate email '{$takenEmail}' (iteration {$i})");
            } catch (UniqueConstraintViolationException) {
                // Assert: no new School was persisted (transaction rolled back)
                $this->assertSame(
                    $schoolCountBefore,
                    School::count(),
                    "Atomicity violated: School record was created despite admin email collision (iteration {$i})"
                );

                // Assert: no new User was persisted either
                $this->assertSame(
                    $userCountBefore,
                    User::count(),
                    "Atomicity violated: extra User record created despite rollback (iteration {$i})"
                );

                // Assert: specifically, no school with the attempted slug exists
                $this->assertDatabaseMissing('schools', ['slug' => $payload['slug']]);
            }
        }
    }

    // ── Invariant 2: Admin always links to the created school ──────

    public function test_property_provision_admin_always_links_to_school(): void
    {
        for ($i = 0; $i < 5; $i++) {
            // Arrange
            $payload = $this->randomPayload();

            // Act
            $result = (new ProvisionSchoolAction)->execute($payload);
            $school = $result['school'];
            $admin = $result['admin'];

            // Assert: admin->school_id is never null
            $this->assertNotNull(
                $admin->school_id,
                "Admin school_id must never be null (iteration {$i})"
            );

            // Assert: admin->school_id === school->id exactly
            $this->assertSame(
                $school->id,
                $admin->school_id,
                "Admin school_id ({$admin->school_id}) must equal school id ({$school->id}) (iteration {$i})"
            );

            // Assert: verified at DB level too
            $this->assertDatabaseHas('users', [
                'id' => $admin->id,
                'school_id' => $school->id,
            ]);
        }
    }

    // ── Invariant 3: Admin role is always school_admin ─────────────

    public function test_property_provision_admin_role_always_school_admin(): void
    {
        for ($i = 0; $i < 5; $i++) {
            // Arrange: use varying payload shapes
            $payload = $this->randomPayload();

            // Act
            $result = (new ProvisionSchoolAction)->execute($payload);
            $admin = $result['admin'];

            // Assert: role is always 'school_admin', regardless of input
            $this->assertSame(
                'school_admin',
                $admin->role,
                "Admin role must be 'school_admin', got '{$admin->role}' (iteration {$i})"
            );

            // Assert: role helper confirms
            $this->assertTrue(
                $admin->isSchoolAdmin(),
                "isSchoolAdmin() must return true for provisioned admin (iteration {$i})"
            );

            // Assert: not super_admin, professor, or aluno
            $this->assertFalse($admin->isSuperAdmin(), "Provisioned admin must not be super_admin (iteration {$i})");
            $this->assertFalse($admin->isProfessor(), "Provisioned admin must not be professor (iteration {$i})");
            $this->assertFalse($admin->isAluno(), "Provisioned admin must not be aluno (iteration {$i})");

            // Assert: DB-level verification
            $this->assertDatabaseHas('users', [
                'id' => $admin->id,
                'role' => 'school_admin',
            ]);
        }
    }

    // ── Invariant 4: Password is always hashed correctly ──────────

    public function test_property_provision_password_always_hashed(): void
    {
        for ($i = 0; $i < 5; $i++) {
            // Arrange: generate a random plaintext password
            $plaintext = Str::random(rand(8, 64));
            $payload = $this->randomPayload(password: $plaintext);

            // Act
            $result = (new ProvisionSchoolAction)->execute($payload);
            $admin = $result['admin'];

            // Assert: stored hash differs from plaintext
            $this->assertNotSame(
                $plaintext,
                $admin->password,
                "Password must be hashed, not stored as plaintext (iteration {$i})"
            );

            // Assert: Hash::check verifies the plaintext against the stored hash
            $this->assertTrue(
                Hash::check($plaintext, $admin->password),
                "Hash::check must return true for original plaintext (iteration {$i})"
            );

            // Assert: wrong password does NOT verify
            $wrongPassword = $plaintext.'_wrong';
            $this->assertFalse(
                Hash::check($wrongPassword, $admin->password),
                "Hash::check must return false for incorrect password (iteration {$i})"
            );

            // Assert: fresh load from DB also verifies
            $freshAdmin = User::find($admin->id);
            $this->assertTrue(
                Hash::check($plaintext, $freshAdmin->password),
                "Hash::check must verify after fresh DB load (iteration {$i})"
            );
        }
    }
}
