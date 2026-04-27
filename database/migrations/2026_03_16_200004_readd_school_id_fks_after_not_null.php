<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 2d of multi-tenant migration — MySQL/MariaDB compatibility layer.
 *
 * Re-adds the school_id foreign keys that were dropped in
 * 2026_03_16_200002_z_drop_school_id_fks_before_not_null.php so that
 * 2026_03_16_200003_make_school_id_not_null_on_tenant_tables.php could
 * flip the column to NOT NULL on MySQL/MariaDB.
 *
 * ON DELETE behaviour:
 *   The original 200001 used nullOnDelete() (ON DELETE SET NULL). That
 *   action is no longer valid for a NOT NULL column, so we instead use
 *   restrictOnDelete() (ON DELETE RESTRICT). The rationale:
 *
 *     - Deleting a school with tenant data is dangerous and almost
 *       certainly a bug. RESTRICT forces explicit cleanup or cascade.
 *     - cascadeOnDelete() would silently delete all classes, lessons,
 *       materials, and payments belonging to a deleted school — a
 *       multi-tenant data-loss hazard.
 *     - SET NULL is not an option (column is NOT NULL).
 *
 *   This is a deliberate semantic upgrade: the original SET NULL was
 *   only ever reachable while school_id was nullable, which it no
 *   longer is. RESTRICT matches the new contract.
 */
return new class extends Migration
{
    /**
     * Tables that received a school_id FK in migration 200001 and had
     * it dropped in 200002_z and re-added here.
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
                $blueprint->foreign('school_id')
                    ->references('id')
                    ->on('schools')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['school_id']);
            });
        }
    }
};
