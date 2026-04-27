<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Eliminates the legacy 'admin' role.
 *
 * Background:
 *   Originally users.role was declared as an enum:
 *     enum('admin', 'professor', 'aluno')
 *   in 0001_01_01_000000_create_users_table.php. The SaaS migration introduced
 *   'super_admin' and 'school_admin' values WITHOUT a schema migration to expand
 *   the enum (this works in SQLite because SQLite ignores ENUM constraints and
 *   stores the column as plain TEXT — see CHECK constraint behaviour). On
 *   MySQL/MariaDB the original schema would reject inserts of those values, but
 *   in this codebase that latent bug is OUT OF SCOPE for this migration and is
 *   carried forward unchanged.
 *
 *   This migration only does what is required to retire the legacy 'admin'
 *   role: it remaps any remaining users with role='admin' to 'school_admin'.
 *
 * Idempotency:
 *   The UPDATE is naturally idempotent — running it twice is safe; the second
 *   run touches zero rows because no user has role='admin' anymore.
 *
 * Reversibility:
 *   None. This is a one-way data cleanup. The down() method intentionally
 *   throws so accidental rollbacks fail loudly instead of silently restoring
 *   inconsistent state.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'school_admin']);
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Cannot revert: legacy admin role was removed by design. '
            .'See migration 2026_04_27_143815_migrate_legacy_admin_role_to_school_admin.'
        );
    }
};
