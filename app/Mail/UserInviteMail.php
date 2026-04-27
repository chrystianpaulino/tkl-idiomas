<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Carries the one-time invite link to a newly-created user.
 *
 * The plain token is passed through the constructor (NOT persisted on the
 * model) and reaches the recipient exclusively via the rendered email body.
 * AcceptInviteController hashes the value back with SHA-256 to look up the
 * user. Because we serialize via SerializesModels, the model is rehydrated
 * from the database on queued dispatch -- the constructor arguments
 * ($token, $invitedBy) are kept in the serialized payload alongside it.
 *
 * The subject mentions the school name when we have one; super_admin
 * invites (school_id = null) fall back to the platform name.
 */
class UserInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $token,
        public readonly User $invitedBy,
    ) {}

    public function envelope(): Envelope
    {
        $context = $this->user->school?->name ?? config('app.name', 'EduGest');

        return new Envelope(
            subject: "Você foi convidado para o {$context}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user-invite',
            with: [
                'user' => $this->user,
                'invitedBy' => $this->invitedBy,
                // Build the URL here rather than in the view so the token
                // never appears in two different places (and is easy to grep).
                'acceptUrl' => route('invite.accept', ['token' => $this->token]),
                'schoolName' => $this->user->school?->name,
                'platformName' => config('app.name', 'EduGest'),
            ],
        );
    }
}
