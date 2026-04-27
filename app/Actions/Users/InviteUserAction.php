<?php

namespace App\Actions\Users;

use App\Mail\UserInviteMail;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Creates a user in "pending invite" state and dispatches the invite email.
 *
 * Replaces the old "admin types a password" flow (Wave 9). The admin only
 * provides name + email + role; the user defines their own password by
 * following the link in the invite email. The password column is filled
 * with a random 64-char string solely so the NOT NULL constraint is
 * satisfied -- the user cannot log in until they accept the invite.
 *
 * SECURITY NOTES:
 *  - $token is the plain (URL-safe) value; it is generated fresh on each
 *    invite and NEVER persisted. We hash it (SHA-256) before storing in
 *    invite_token so a database leak does not let attackers accept pending
 *    invites.
 *  - The audit log records `user.invited` but never includes the plain
 *    token (Audit::log itself redacts known sensitive keys, but we omit
 *    the token entirely as defense-in-depth).
 *  - The `role` and `school_id` columns are excluded from User::$fillable;
 *    they are set via direct attribute assignment.
 *
 * The whole flow runs in a DB transaction so a Mail dispatch failure does
 * not leave a half-created invite stuck in the database.
 *
 * @see UserInviteMail   Mailable rendered from this action
 * @see ResendInviteAction  Reuses the same hash/dispatch logic for re-sends
 */
class InviteUserAction
{
    /**
     * @param  array{name: string, email: string, role: string, phone?: string|null}  $data
     * @param  User  $invitedBy  The actor who initiated the invite (for the email body and audit context).
     * @param  int|null  $schoolId  Tenant. school_admin passes their own school_id; super_admin may pass null.
     */
    public function execute(array $data, User $invitedBy, ?int $schoolId = null): User
    {
        return DB::transaction(function () use ($data, $invitedBy, $schoolId) {
            // 48 chars of base64 is ~288 bits of entropy: well above the
            // ~128-bit floor for unguessable session-equivalent tokens.
            // Str::random returns alphanumerics, which travel through URLs
            // without percent-encoding.
            $token = Str::random(48);

            $user = new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            // Random password the user can never know -- they will replace
            // it via AcceptInviteController. Done explicitly because some
            // login attempts hit Hash::check before AcceptInvite, and a
            // NULL password would crash bcrypt.
            $user->password = Hash::make(Str::random(64));
            $user->role = $data['role'];
            $user->school_id = $schoolId;
            $user->phone = $data['phone'] ?? null;
            $user->invite_token = hash('sha256', $token);
            $user->invite_sent_at = now();
            $user->save();

            Mail::to($user)->send(new UserInviteMail($user, $token, $invitedBy));

            // Audit captures who, what role, and which tenant. Plain token
            // is intentionally omitted so it never touches the audit log.
            Audit::log('user.invited', [
                'invited_user_id' => $user->id,
                'invited_email' => $user->email,
                'invited_role' => $user->role,
                'invited_school_id' => $user->school_id,
            ]);

            return $user;
        });
    }
}
