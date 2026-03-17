<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_list_id')->constrained('exercise_lists')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamps();

            $table->unique(['exercise_list_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_submissions');
    }
};
