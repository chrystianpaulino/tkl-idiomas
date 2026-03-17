<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->enum('status', ['scheduled', 'confirmed', 'cancelled'])->default('scheduled');
            $table->text('cancelled_reason')->nullable();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->timestamps();

            $table->index('scheduled_at');
            $table->index('class_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_lessons');
    }
};
