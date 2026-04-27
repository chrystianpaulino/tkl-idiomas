<?php

namespace App\Actions\Users;

use App\Mail\UserInviteMail;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Reissues a fresh invite link for a user whose invite expired or was lost.
 *
 * Generates a new plain token, replaces the stored hash, refreshes
 * invite_sent_at, and dispatches a new email. Any previous link becomes
 * unusable as soon as the hash is overwritten -- exactly the property we
 * want when an invite is suspected leaked or simply went past its 7-day TTL.
 *
 * Pre-condition: the user must still have a pending invite (i.e. has not
 * yet accepted). Guard enforced in UserPolicy::resendInvite, but we also
 * defensively reject already-accepted users here so a misconfigured policy
 * cannot silently re-send a token to a fully active account.
 */
class ResendInviteAction
{
    public function execute(User $user): User
    {
        return DB::transaction(function () use ($user) {
            // Defense-in-depth: an accepted invite has a real, user-chosen
            // password. Issuing a new token would give the original admin
            // a back-door to take over the account. The policy already blocks
            // this path; this throw turns a misconfiguration into a loud
            // failure rather than silent privilege escalation.
            if ($user->invite_accepted_at !== null) {
                throw new \LogicException(
                    'Cannot resend invite for a user who has already accepted.'
                );
            }

            $token = Str::random(48);

            $user->invite_token = hash('sha256', $token);
            $user->invite_sent_at = now();
            $user->save();

            Mail::to($user)->send(new UserInviteMail($user, $token, Auth::user() ?? $user));

            Audit::log('user.invite_resent', [
                'invited_user_id' => $user->id,
                'invited_email' => $user->email,
                'invited_role' => $user->role,
                'invited_school_id' => $user->school_id,
            ]);

            return $user;
        });
    }
}
