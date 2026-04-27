<?php

namespace Tests\Feature\Auth;

use App\Mail\UserInviteMail;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage for the Wave 9 invite flow.
 *
 * The flow has three distinct surfaces -- creation (POST /admin/users),
 * acceptance (GET/POST /invite/{token}), and resend (POST /admin/users/{u}/invite/resend) --
 * and several invariants that span them (token is hashed, single-use, expires
 * after 7 days). Each invariant gets its own focused test below.
 */
class InviteFlowTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->admin = User::factory()->create(['school_id' => $this->school->id]);
        $this->admin->role = 'school_admin';
        $this->admin->save();
    }

    protected function tearDown(): void
    {
        // BelongsToSchool global scope reads from the container; null it out
        // so subsequent tests start clean.
        app()->forgetInstance('tenant.school_id');

        parent::tearDown();
    }

    /**
     * Helper: create a user with a fresh invite (hashed) so acceptance tests
     * do not depend on the email-dispatch side of the flow.
     *
     * Returns [User, plainToken] -- the plainToken is what would have been
     * embedded in the email body.
     *
     * @return array{0: User, 1: string}
     */
    private function makePendingInvitee(array $overrides = []): array
    {
        $plain = Str::random(48);

        $user = User::factory()->unverified()->create(array_merge([
            'school_id' => $this->school->id,
            'invite_token' => hash('sha256', $plain),
            'invite_sent_at' => now(),
            'invite_accepted_at' => null,
        ], $overrides));
        $user->role = 'aluno';
        $user->save();

        return [$user, $plain];
    }

    // ── Creation (POST /admin/users) ─────────────────────────────────────

    public function test_school_admin_creates_user_dispatches_invite_email(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'New Aluno',
            'email' => 'new.aluno@example.com',
            'role' => 'aluno',
        ])->assertRedirect();

        // Mail::fake captures the dispatch without sending.
        Mail::assertSent(UserInviteMail::class, function (UserInviteMail $mail) {
            return $mail->hasTo('new.aluno@example.com');
        });

        $this->assertDatabaseHas('users', [
            'email' => 'new.aluno@example.com',
            'role' => 'aluno',
            'school_id' => $this->school->id,
            'email_verified_at' => null,
        ]);
    }

    public function test_invite_token_is_stored_hashed_in_database(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Hash Probe',
            'email' => 'hash.probe@example.com',
            'role' => 'aluno',
        ]);

        $user = User::where('email', 'hash.probe@example.com')->firstOrFail();

        // The stored token MUST be a SHA-256 hex (64 chars) -- never the
        // plain Str::random(48) value (alphanumeric, length 48).
        $this->assertNotNull($user->invite_token);
        $this->assertSame(64, strlen($user->invite_token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $user->invite_token);
    }

    // ── Acceptance (GET /invite/{token}) ─────────────────────────────────

    public function test_accept_invite_page_renders_for_valid_token(): void
    {
        [, $plain] = $this->makePendingInvitee();

        $response = $this->get(route('invite.accept', ['token' => $plain]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Auth/AcceptInvite'));
    }

    public function test_accept_invite_page_shows_expired_view_for_invalid_token(): void
    {
        $response = $this->get(route('invite.accept', ['token' => 'totally-bogus-token']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Auth/InviteExpired'));
    }

    public function test_accept_invite_page_shows_expired_view_for_old_invite(): void
    {
        // 8 days ago -- past the 7-day TTL.
        [, $plain] = $this->makePendingInvitee([
            'invite_sent_at' => now()->subDays(8),
        ]);

        $response = $this->get(route('invite.accept', ['token' => $plain]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Auth/InviteExpired'));
    }

    // ── Acceptance (POST /invite/{token}) ────────────────────────────────

    public function test_accept_invite_creates_password_marks_verified_and_logs_in(): void
    {
        [$user, $plain] = $this->makePendingInvitee();

        $response = $this->post(route('invite.store', ['token' => $plain]), [
            'password' => 'StrongPass!2026',
            'password_confirmation' => 'StrongPass!2026',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->invite_accepted_at);
        $this->assertNull($user->invite_token);
    }

    public function test_accept_invite_clears_token_after_use(): void
    {
        [, $plain] = $this->makePendingInvitee();

        // First acceptance: succeeds.
        $this->post(route('invite.store', ['token' => $plain]), [
            'password' => 'StrongPass!2026',
            'password_confirmation' => 'StrongPass!2026',
        ])->assertRedirect(route('dashboard'));

        // Logout so the second request is anonymous, mirroring an attacker
        // re-using the link.
        $this->post(route('logout'));

        // Second acceptance with the same plain token: rejected, redirect
        // to login (token was cleared, so resolveValidUser returns null).
        $second = $this->post(route('invite.store', ['token' => $plain]), [
            'password' => 'AnotherPass!2026',
            'password_confirmation' => 'AnotherPass!2026',
        ]);

        $second->assertRedirect(route('login'));
    }

    public function test_accept_invite_request_validates_password_strength(): void
    {
        [, $plain] = $this->makePendingInvitee();

        // Weak password -- fails Password::defaults() (no symbol, no number).
        $response = $this->post(route('invite.store', ['token' => $plain]), [
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    // ── Resend (POST /admin/users/{user}/invite/resend) ──────────────────

    public function test_school_admin_can_resend_invite_for_own_school_user(): void
    {
        Mail::fake();

        [$invitee, $oldPlain] = $this->makePendingInvitee();
        $oldHash = $invitee->invite_token;

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite.resend', $invitee));

        $response->assertRedirect();

        $invitee->refresh();
        // A fresh token must replace the old hash.
        $this->assertNotNull($invitee->invite_token);
        $this->assertNotSame($oldHash, $invitee->invite_token);
        // The old plain token must no longer authenticate the new hash.
        $this->assertNotSame(hash('sha256', $oldPlain), $invitee->invite_token);

        Mail::assertSent(UserInviteMail::class, fn ($mail) => $mail->hasTo($invitee->email));
    }

    public function test_super_admin_can_resend_invite(): void
    {
        Mail::fake();

        $superAdmin = User::factory()->create(['school_id' => null]);
        $superAdmin->role = 'super_admin';
        $superAdmin->save();

        [$invitee] = $this->makePendingInvitee();

        $this->actingAs($superAdmin)
            ->post(route('admin.users.invite.resend', $invitee))
            ->assertRedirect();

        Mail::assertSent(UserInviteMail::class);
    }

    public function test_school_admin_cannot_resend_invite_for_other_school_user(): void
    {
        Mail::fake();

        $otherSchool = School::factory()->create();
        [$otherInvitee] = $this->makePendingInvitee([
            'school_id' => $otherSchool->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.invite.resend', $otherInvitee))
            ->assertForbidden();

        Mail::assertNotSent(UserInviteMail::class);
    }

    public function test_resend_invite_fails_for_already_accepted_user(): void
    {
        Mail::fake();

        // Accepted invitee: invite_accepted_at is set, invite_token is null.
        $accepted = User::factory()->create([
            'school_id' => $this->school->id,
            'invite_token' => null,
            'invite_sent_at' => now()->subDay(),
            'invite_accepted_at' => now()->subDay(),
        ]);
        $accepted->role = 'aluno';
        $accepted->save();

        // Policy::resendInvite returns false when hasPendingInvite is false.
        $this->actingAs($this->admin)
            ->post(route('admin.users.invite.resend', $accepted))
            ->assertForbidden();

        Mail::assertNotSent(UserInviteMail::class);
    }
}
