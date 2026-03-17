<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_list_id')->constrained('exercise_lists')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(1);
            $table->text('question');
            $table->enum('type', ['text', 'file'])->default('text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
