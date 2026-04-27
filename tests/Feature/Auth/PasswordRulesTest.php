<?php

namespace Tests\Feature\Auth;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the strict Password::defaults() rule registered in
 * AppServiceProvider::boot().
 *
 * Required: 12+ chars, mixed case, numbers, symbols. The ->uncompromised()
 * check is only enabled in production, so test/dev environments do not call
 * out to haveibeenpwned.
 *
 * Wave 9 (invite flow) removed the password field from POST /admin/users.
 * Password::defaults() is still applied wherever a user actually chooses a
 * password: the invite-acceptance form, the password reset form, and the
 * password update form. Acceptance is exercised here because it is the only
 * write endpoint reachable without a pre-existing authenticated session.
 */
class PasswordRulesTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $invitee;

    /**
     * The PLAIN token; what the email would carry. The DB stores
     * hash('sha256', $token) so we can look the user up without ever
     * persisting the value.
     */
    private string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();

        // Build an invitee with a fresh, valid invite token. We stamp the
        // hash directly to keep this test independent from
        // InviteUserAction's email side-effects (Mail::fake is unnecessary).
        $this->plainToken = Str::random(48);

        $this->invitee = User::factory()->unverified()->create([
            'school_id' => $this->school->id,
            'invite_token' => hash('sha256', $this->plainToken),
            'invite_sent_at' => now(),
        ]);
        $this->invitee->role = 'aluno';
        $this->invitee->save();
    }

    protected function tearDown(): void
    {
        // Prevent tenant context leak between tests (BelongsToSchool global scope).
        app()->forgetInstance('tenant.school_id');

        parent::tearDown();
    }

    private function postAccept(string $password)
    {
        return $this->post(route('invite.store', ['token' => $this->plainToken]), [
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_accept_invite_rejects_password_below_12_chars(): void
    {
        // 11 chars with full complexity (mixed case, number, symbol).
        // Fails purely on the length floor of Password::defaults() (min:12).
        $shortButComplex = 'Strong!23aB';
        $this->assertSame(11, strlen($shortButComplex));

        $response = $this->postAccept($shortButComplex);

        $response->assertSessionHasErrors('password');
    }

    public function test_accept_invite_rejects_password_without_uppercase(): void
    {
        $response = $this->postAccept('strongpass!2026');

        $response->assertSessionHasErrors('password');
    }

    public function test_accept_invite_rejects_password_without_lowercase(): void
    {
        $response = $this->postAccept('STRONGPASS!2026');

        $response->assertSessionHasErrors('password');
    }

    public function test_accept_invite_rejects_password_without_number(): void
    {
        $response = $this->postAccept('StrongPass!XYZ@');

        $response->assertSessionHasErrors('password');
    }

    public function test_accept_invite_rejects_password_without_symbol(): void
    {
        $response = $this->postAccept('StrongPass2026X');

        $response->assertSessionHasErrors('password');
    }

    public function test_accept_invite_accepts_strong_password(): void
    {
        $response = $this->postAccept('StrongPass!2026');

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard'));

        $this->invitee->refresh();
        $this->assertNotNull($this->invitee->email_verified_at);
        $this->assertNull($this->invitee->invite_token);
        $this->assertNotNull($this->invitee->invite_accepted_at);
    }
}
