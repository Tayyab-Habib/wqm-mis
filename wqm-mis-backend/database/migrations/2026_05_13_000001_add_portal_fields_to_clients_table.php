<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Pre-created password (plain text stored for display, hashed for auth)
            $table->string('password')->nullable()->after('email');
            $table->string('portal_token', 80)->nullable()->unique()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['password', 'portal_token']);
        });
    }
};
