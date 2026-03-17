<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lesson_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            // TODO(review): No DB CHECK constraint for used_lessons <= total_lessons. Enforced at application layer via RegisterLessonAction lockForUpdate(). - business-logic-reviewer, 2026-03-12, Severity: Medium
            $table->unsignedInteger('total_lessons');
            $table->unsignedInteger('used_lessons')->default(0);
            $table->timestamp('purchased_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->index('student_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_packages');
    }
};
