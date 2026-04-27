<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 9: Invite flow.
 *
 * The admin no longer sets the password for new users. Instead, an invite
 * email carries a single-use signed link the recipient clicks to define their
 * own password. The columns added here track that handshake:
 *
 *   - invite_token        SHA-256 hash of the plain token sent in the email.
 *                         The plain token is NEVER persisted -- only its hash --
 *                         so a database leak does not let attackers accept
 *                         pending invites. Unique because the hash uniquely
 *                         identifies the invite during AcceptInviteController.
 *   - invite_sent_at      Timestamp of the most recent dispatch. Used to
 *                         enforce the 7-day expiration window without needing
 *                         a separate `expires_at` column.
 *   - invite_accepted_at  Set when the user defines their password. Once set,
 *                         invite_token is cleared (so the link cannot be
 *                         re-used) and the user is auto-verified.
 *
 * For pre-existing users (seeded admins and any users created before this
 * migration ran), all three columns default to NULL -- they bypass the invite
 * flow entirely and continue logging in normally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('invite_token', 64)->nullable()->unique()->after('remember_token');
            $table->timestamp('invite_sent_at')->nullable()->after('invite_token');
            $table->timestamp('invite_accepted_at')->nullable()->after('invite_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the unique index explicitly before the column on MySQL --
            // SQLite does not require this but the explicit call is a no-op
            // there and keeps the migration portable across both engines.
            $table->dropUnique(['invite_token']);
            $table->dropColumn(['invite_token', 'invite_sent_at', 'invite_accepted_at']);
        });
    }
};
