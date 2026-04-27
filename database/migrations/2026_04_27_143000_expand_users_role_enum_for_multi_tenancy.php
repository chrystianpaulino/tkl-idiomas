<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Expands the users.role ENUM to include the new SaaS roles introduced when
 * the platform pivoted from a single-school product to a multi-tenant SaaS:
 *
 *     'super_admin'   — platform owner, no tenant scope
 *     'school_admin'  — tenant-level administrator
 *
 * Background:
 *   The original migration 0001_01_01_000000_create_users_table.php declared
 *   role as ENUM('admin','professor','aluno'). On SQLite (the original dev
 *   driver), ENUM is implemented as plain TEXT with no value validation, so
 *   inserts/updates of 'super_admin' and 'school_admin' silently worked. On
 *   MySQL/MariaDB they fail with "Data truncated for column 'role'".
 *
 * Why 'admin' is preserved here:
 *   The next migration in the chain — 2026_04_27_143815_migrate_legacy_admin_
 *   role_to_school_admin — runs UPDATE users SET role='school_admin'
 *   WHERE role='admin'. For that UPDATE to even SELECT rows where role='admin'
 *   on MySQL, 'admin' must still be a valid enum value at that moment.
 *   We drop 'admin' from the enum AFTER the legacy migration in a separate
 *   migration: 2026_04_27_143900_remove_legacy_admin_from_users_role_enum.
 *
 * Driver awareness:
 *   SQLite does not support `ALTER TABLE ... MODIFY COLUMN`. On SQLite the
 *   role column is already plain TEXT (it never had real ENUM enforcement),
 *   so no schema change is needed. We no-op on SQLite to keep the test
 *   environment (phpunit.xml uses sqlite :memory:) working.
 *
 * Idempotency:
 *   Running this on a database that already has the expanded enum is a no-op
 *   (re-issuing MODIFY with the same definition is benign). Running on SQLite
 *   is always a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement(
                'ALTER TABLE users MODIFY COLUMN role '
                ."ENUM('super_admin','school_admin','admin','professor','aluno') "
                ."NOT NULL DEFAULT 'aluno'"
            );
        }
        // SQLite: no-op (ENUM is plain TEXT, all values already accepted).
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // Best-effort revert to the original enum. This may fail if any
            // user currently has role='super_admin' or 'school_admin' (which
            // is the entire point of this migration), but that is the
            // intended semantics of a rollback to the pre-SaaS schema.
            DB::statement(
                'ALTER TABLE users MODIFY COLUMN role '
                ."ENUM('admin','professor','aluno') "
                ."NOT NULL DEFAULT 'aluno'"
            );
        }
        // SQLite: no-op.
    }
};
