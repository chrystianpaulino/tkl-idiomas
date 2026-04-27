<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds white-label visual identity columns to the schools table.
 *
 * Each tenant (school) can now upload its own logo and pick its own brand colors,
 * which are surfaced via Inertia and applied as CSS variables on authenticated pages.
 *
 * Default colors mirror the platform's current Tailwind design tokens:
 *   - primary_color   '#4f46e5'  → indigo-600 (accent / primary buttons)
 *   - secondary_color '#0f172a'  → slate-900  (sidebar background)
 *
 * These defaults guarantee that schools created before customization look identical
 * to the legacy hardcoded UI — zero visual regression.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('email');
            $table->string('primary_color', 7)->default('#4f46e5')->after('logo_url');
            $table->string('secondary_color', 7)->default('#0f172a')->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['logo_url', 'primary_color', 'secondary_color']);
        });
    }
};
