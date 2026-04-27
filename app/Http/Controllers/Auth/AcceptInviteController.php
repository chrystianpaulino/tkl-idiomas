<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInviteRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\RoleLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the invitee side of the Wave 9 invite flow.
 *
 * Both endpoints are exposed to anonymous traffic (`guest` middleware in
 * routes/auth.php) -- the bearer of a valid token IS authenticated as the
 * target user for the duration of password setup. Token validity has three
 * dimensions, all enforced here:
 *
 *   1. Hash match: the URL-borne plain token, hashed with SHA-256, must equal
 *      the stored invite_token. We never compare plain values, so a database
 *      dump does not let an attacker hijack pending invites.
 *   2. Single-use: invite_accepted_at must be NULL. Once accepted the token
 *      is also blanked, but checking accepted_at first means a request that
 *      arrives after acceptance still gets a clean 404 even if the token was
 *      somehow not cleared.
 *   3. Freshness: invite_sent_at must be within the last 7 days. Older
 *      invites force the admin to issue a fresh link, capping how long a
 *      stolen email window stays exploitable.
 *
 * Failure of ANY check yields the same expired-link view -- distinguishing
 * "invalid token" from "expired token" would help an attacker enumerate
 * tokens, so they share a single response shape (with a generic message).
 */
class AcceptInviteController extends Controller
{
    /**
     * Maximum age of an invite_sent_at before the link must be reissued.
     * Mirrored in UserInviteMail's body copy.
     */
    private const INVITE_TTL_DAYS = 7;

    public function show(string $token): Response
    {
        $user = $this->resolveValidUser($token);

        if ($user === null) {
            return Inertia::render('Auth/InviteExpired', [
                'platformName' => config('app.name', 'EduGest'),
            ]);
        }

        return Inertia::render('Auth/AcceptInvite', [
            'token' => $token,
            'invitee' => [
                'name' => $user->name,
                'email' => $user->email,
                'role_label' => RoleLabels::for($user->role),
            ],
            'school' => $user->school?->only('id', 'name'),
            'platformName' => config('app.name', 'EduGest'),
        ]);
    }

    public function accept(AcceptInviteRequest $request, string $token): RedirectResponse
    {
        $user = $this->resolveValidUser($token);

        if ($user === null) {
            // Same response shape as expired GET so the redirect doesn't leak
            // whether the token was wrong vs already used vs expired.
            return redirect()->route('login')
                ->with('error', 'Este link de convite expirou ou já foi utilizado. Peça um novo convite ao administrador.');
        }

        $user->password = Hash::make($request->validated('password'));
        $user->markEmailAsVerified();
        $user->invite_token = null;
        $user->invite_accepted_at = now();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        Audit::log('user.invite_accepted', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'school_id' => $user->school_id,
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Performs all three token validity checks (hash match, not yet accepted,
     * within TTL) and returns the matching user, or null if any check fails.
     *
     * Eager-loads the school relation because both the show() view and the
     * audit log read it -- avoids two extra queries on the happy path.
     */
    private function resolveValidUser(string $token): ?User
    {
        $hash = hash('sha256', $token);

        $user = User::with('school')
            ->where('invite_token', $hash)
            ->whereNull('invite_accepted_at')
            ->first();

        if ($user === null) {
            return null;
        }

        // invite_sent_at is non-null whenever invite_token is set (the
        // InviteUserAction always stamps both), but we guard defensively
        // in case a future migration introduces back-fill rows.
        if ($user->invite_sent_at === null
            || $user->invite_sent_at->lt(now()->subDays(self::INVITE_TTL_DAYS))) {
            return null;
        }

        return $user;
    }
}
