<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->decimal('price', 8, 2)->nullable()->after('total_lessons');
            $table->string('currency', 3)->default('BRL')->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->dropColumn(['price', 'currency']);
        });
    }
};
