<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 2a of multi-tenant migration.
 *
 * Creates the default school and assigns ALL existing records to it.
 *
 * Safe to run on a database with existing data:
 *  - Uses first-or-create for the school (idempotent).
 *  - Only updates records WHERE school_id IS NULL.
 *  - Derives school_id from parent relationships where possible.
 *  - Asserts zero nulls remain before finishing.
 *
 * NOTE: users.school_id is intentionally kept nullable (super_admin has no school).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Resolve (or create) the default school ─────────────────────────

        $existing = DB::table('schools')->where('slug', 'tkl-idiomas')->first();

        if ($existing) {
            $schoolId = $existing->id;
        } else {
            $schoolId = DB::table('schools')->insertGetId([
                'name' => 'TKL Idiomas',
                'slug' => 'tkl-idiomas',
                'email' => null,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── 2. Users — assign to default school where unassigned ──────────────
        // Intentionally uses WHERE NULL so future schools can pre-assign users.

        DB::table('users')
            ->whereNull('school_id')
            ->update(['school_id' => $schoolId]);

        // ── 3. Classes — derive from professor's school_id ────────────────────
        // All professors were just assigned to $schoolId above, so this is
        // effectively a bulk update. The JOIN is explicit so future runs on
        // partially migrated databases derive correctly.

        DB::statement('
            UPDATE classes
            SET school_id = (
                SELECT school_id FROM users WHERE users.id = classes.professor_id
            )
            WHERE classes.school_id IS NULL
        ');

        // ── 4. Lesson packages — derive from student's school_id ──────────────

        DB::statement('
            UPDATE lesson_packages
            SET school_id = (
                SELECT school_id FROM users WHERE users.id = lesson_packages.student_id
            )
            WHERE lesson_packages.school_id IS NULL
        ');

        // ── 5. Lessons — derive from class's school_id (classes was populated first) ─

        DB::statement('
            UPDATE lessons
            SET school_id = (
                SELECT school_id FROM classes WHERE classes.id = lessons.class_id
            )
            WHERE lessons.school_id IS NULL
        ');

        // ── 6. Materials — derive from class's school_id ──────────────────────

        DB::statement('
            UPDATE materials
            SET school_id = (
                SELECT school_id FROM classes WHERE classes.id = materials.class_id
            )
            WHERE materials.school_id IS NULL
        ');

        // ── 7. Payments — derive from student's school_id ─────────────────────

        DB::statement('
            UPDATE payments
            SET school_id = (
                SELECT school_id FROM users WHERE users.id = payments.student_id
            )
            WHERE payments.school_id IS NULL
        ');

        // ── 8. Safety assertion — no orphan records allowed ───────────────────

        $tables = ['classes', 'lesson_packages', 'lessons', 'materials', 'payments'];

        foreach ($tables as $table) {
            $nullCount = DB::table($table)->whereNull('school_id')->count();

            if ($nullCount > 0) {
                throw new RuntimeException(
                    "Migration failed: {$nullCount} record(s) in '{$table}' still have school_id = NULL. ".
                    'Investigate before applying the NOT NULL constraint.'
                );
            }
        }
    }

    public function down(): void
    {
        // Revert: clear school_id from all records that were populated.
        // This restores the state from Step 1 (nullable but unpopulated).
        // The default school itself is NOT deleted — it may have been modified.

        DB::table('payments')->update(['school_id' => null]);
        DB::table('materials')->update(['school_id' => null]);
        DB::table('lessons')->update(['school_id' => null]);
        DB::table('lesson_packages')->update(['school_id' => null]);
        DB::table('classes')->update(['school_id' => null]);
        DB::table('users')->update(['school_id' => null]);
    }
};
