<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 2b of multi-tenant migration.
 *
 * Enforces NOT NULL on school_id for all tenant-scoped tables.
 *
 * PREREQUISITE: Migration 200002 must have run successfully and all
 * school_id columns must be populated (zero NULLs).
 *
 * NOTE: users.school_id deliberately remains nullable — the future
 * super_admin role belongs to no school.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard: refuse to run if any table still has NULL school_ids.
        // This prevents accidentally adding a NOT NULL constraint with orphan rows.
        $tables = ['classes', 'lesson_packages', 'lessons', 'materials', 'payments'];

        foreach ($tables as $table) {
            $nullCount = DB::table($table)->whereNull('school_id')->count();

            if ($nullCount > 0) {
                throw new \RuntimeException(
                    "Cannot enforce NOT NULL: {$nullCount} record(s) in '{$table}' have school_id = NULL. " .
                    'Run migration 200002 first and verify all records are assigned to a school.'
                );
            }
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable(false)->change();
        });

        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable(false)->change();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable(false)->change();
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable(false)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // Revert: make all columns nullable again.
        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->change();
        });

        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->change();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->change();
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->change();
        });
    }
};
