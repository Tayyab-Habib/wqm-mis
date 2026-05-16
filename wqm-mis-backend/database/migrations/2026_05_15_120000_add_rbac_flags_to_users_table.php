<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RBAC scaffolding columns on users:
     *   is_view_only     — read-only flag (Director Labs / Super Admin View-Only)
     *   is_dummy         — demo accounts; writes are silently dropped by middleware
     *   allowed_modules  — JSON array of module slugs for the "General View Account"
     *                      role; SA configures per-user which modules are visible
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_view_only')) {
                $table->boolean('is_view_only')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('users', 'is_dummy')) {
                $table->boolean('is_dummy')->default(false)->after('is_view_only');
            }
            if (!Schema::hasColumn('users', 'allowed_modules')) {
                $table->json('allowed_modules')->nullable()->after('is_dummy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['is_view_only', 'is_dummy', 'allowed_modules'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
