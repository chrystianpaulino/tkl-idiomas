<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('lesson_package_id')->constrained('lesson_packages')->restrictOnDelete();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 8, 2);
            $table->string('currency', 3)->default('BRL');
            $table->enum('method', ['pix', 'cash', 'card', 'transfer', 'other'])->default('pix');
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('student_id');
            $table->index('lesson_package_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
