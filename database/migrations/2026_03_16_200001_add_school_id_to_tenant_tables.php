<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of multi-tenant migration.
 *
 * Adds a nullable school_id FK to all tables that must be tenant-scoped.
 * Columns are nullable at this stage — data migration (Step 2) will populate
 * them before they are made NOT NULL (Step 3).
 *
 * Tables: classes, lesson_packages, lessons, materials, payments
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('school_id')
                ->nullable()
                ->after('id')
                ->constrained('schools')
                ->nullOnDelete();
        });

        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->foreignId('school_id')
                ->nullable()
                ->after('id')
                ->constrained('schools')
                ->nullOnDelete();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('school_id')
                ->nullable()
                ->after('id')
                ->constrained('schools')
                ->nullOnDelete();
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('school_id')
                ->nullable()
                ->after('id')
                ->constrained('schools')
                ->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('school_id')
                ->nullable()
                ->after('id')
                ->constrained('schools')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });

        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });
    }
};
