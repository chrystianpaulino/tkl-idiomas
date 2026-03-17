<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->enum('status', [
                'scheduled',
                'completed',
                'cancelled',
                'absent_excused',
                'absent_unexcused',
            ])->default('completed')->after('conducted_at');

            $table->timestamp('scheduled_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['status', 'scheduled_at']);
        });
    }
};
