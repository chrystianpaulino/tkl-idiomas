<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Final step of the users.role enum cleanup.
 *
 * Sequence:
 *   1. 2026_04_27_143000_expand_users_role_enum_for_multi_tenancy
 *      → enum becomes ('super_admin','school_admin','admin','professor','aluno')
 *   2. 2026_04_27_143815_migrate_legacy_admin_role_to_school_admin
 *      → data migration: UPDATE users SET role='school_admin' WHERE role='admin'
 *   3. THIS MIGRATION (2026_04_27_143900)
 *      → enum becomes ('super_admin','school_admin','professor','aluno')
 *
 * Safety guard:
 *   We refuse to shrink the enum if any user still holds role='admin'. If the
 *   data migration did not run (or failed silently), this guard prevents data
 *   loss/truncation.
 *
 * Driver awareness:
 *   See 2026_04_27_143000 for rationale. SQLite ENUM is unenforced TEXT, so
 *   this migration is a no-op on SQLite. Tests run on SQLite :memory: and are
 *   unaffected.
 *
 * Reversibility:
 *   down() restores the 5-value enum (re-introducing the legacy 'admin') so
 *   that 143815 can be rolled back without breaking constraint violations.
 *   This is best-effort: rolling back further requires manual data fixup.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // Defense-in-depth: ensure no row still holds the legacy value.
            $legacyCount = DB::table('users')->where('role', 'admin')->count();

            if ($legacyCount > 0) {
                throw new RuntimeException(
                    "Cannot drop legacy 'admin' from users.role enum: ".
                    "{$legacyCount} row(s) still have role='admin'. ".
                    'Run migration 2026_04_27_143815_migrate_legacy_admin_role_to_school_admin first.'
                );
            }

            DB::statement(
                'ALTER TABLE users MODIFY COLUMN role '
                ."ENUM('super_admin','school_admin','professor','aluno') "
                ."NOT NULL DEFAULT 'aluno'"
            );
        }
        // SQLite: no-op.
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // Restore the 5-value transitional enum so 143815 can be rolled back.
            DB::statement(
                'ALTER TABLE users MODIFY COLUMN role '
                ."ENUM('super_admin','school_admin','admin','professor','aluno') "
                ."NOT NULL DEFAULT 'aluno'"
            );
        }
        // SQLite: no-op.
    }
};
