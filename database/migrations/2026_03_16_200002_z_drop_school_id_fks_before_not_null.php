<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 2c of multi-tenant migration — MySQL/MariaDB compatibility layer.
 *
 * Background:
 *   Migration 200001 created school_id FKs with `nullOnDelete()`
 *   (i.e. ON DELETE SET NULL). Migration 200003 then attempts to flip
 *   school_id to NOT NULL via ->change().
 *
 *   On MySQL/MariaDB this combination is rejected:
 *
 *     ERROR 1830: Column 'school_id' cannot be NOT NULL:
 *                 needed in a foreign key constraint ... SET NULL
 *
 *   A NOT NULL column cannot serve as the local side of an ON DELETE
 *   SET NULL constraint, because the SET NULL action would itself
 *   violate the NOT NULL contract. SQLite silently allows this combo
 *   (it does not validate FK actions against column constraints), which
 *   is why the bug was invisible in dev/tests.
 *
 *   This migration breaks the deadlock: drop the FKs (and their indexes)
 *   so 200003 can flip the columns to NOT NULL. Migration 200004 then
 *   re-adds the FKs with the appropriate ON DELETE behaviour for a
 *   non-nullable column (RESTRICT — see 200004 for rationale).
 *
 * Naming:
 *   The 'z_' prefix forces this file to sort after
 *   '2026_03_16_200002_seed_default_school_and_populate_school_ids.php'
 *   (alphabetic: 's' < 'z') so the sequence is guaranteed:
 *     200002_seed → 200002_z_drop → 200003_not_null → 200004_readd
 *
 * Idempotency:
 *   Each dropForeign + dropIndex pair runs unconditionally; if a fresh
 *   database does not yet have the FK (impossible given migration order),
 *   Laravel's blueprint will throw — this is a hard precondition that
 *   200001 must have run first.
 */
return new class extends Migration
{
    /**
     * Tables that received a school_id FK in migration 200001.
     */
    private array $tables = [
        'classes',
        'lesson_packages',
        'lessons',
        'materials',
        'payments',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['school_id']);
            });
        }
    }

    public function down(): void
    {
        // Recreate the FKs with the original nullOnDelete() behaviour so a
        // rollback restores the exact post-200001 state.
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('school_id')
                    ->references('id')
                    ->on('schools')
                    ->nullOnDelete();
            });
        }
    }
};
