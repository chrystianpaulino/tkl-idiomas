<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->tinyInteger('weekday'); // 0=Sun, 1=Mon, ..., 6=Sat
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('class_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
