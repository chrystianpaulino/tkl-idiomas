<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /**
     * Wave 9: User now implements MustVerifyEmail. Existing seed accounts
     * (super, admin, professors, students) must be flagged as verified
     * during seeding, otherwise the `verified` middleware blocks login on
     * fresh dev environments.
     */
    public function test_seed_accounts_have_verified_emails(): void
    {
        Artisan::call('db:seed', ['--force' => true]);

        $expectedEmails = [
            'super@tkl.com',
            'admin@tkl.com',
            'ana.silva@tkl.com',
            'alice.ferreira@example.com',
        ];

        foreach ($expectedEmails as $email) {
            $user = User::where('email', $email)->first();
            $this->assertNotNull($user, "Seed account {$email} not found.");
            $this->assertNotNull(
                $user->email_verified_at,
                "Seed account {$email} must have email_verified_at set."
            );
        }
    }

    /**
     * Wave 9 sanity check: the `verified` middleware now actually blocks
     * unverified users (it was a no-op before User implemented MustVerifyEmail).
     */
    public function test_unverified_user_redirected_to_verification_page(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('verification.notice'));
    }
}
