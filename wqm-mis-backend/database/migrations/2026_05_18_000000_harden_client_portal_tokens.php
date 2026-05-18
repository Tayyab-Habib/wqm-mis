<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client portal security hardening (2026-05-18):
 *
 *   1. Add `portal_token_expires_at` timestamp so middleware can reject
 *      expired tokens instead of treating them as valid forever.
 *
 *   2. Clear any existing plaintext `portal_token` values. Going forward
 *      the middleware will hash incoming tokens (sha256) and compare
 *      against the stored hash — so the old plaintext values would
 *      never match the new lookup logic anyway. Clearing them out also
 *      removes the plaintext-token-in-DB exposure: anyone with any
 *      active client portal session needs to log in again once. Small
 *      one-time UX disruption, big security win.
 *
 * The portal_token column itself is reused — same name, same type — but
 * the contents going forward will be a sha256 hex hash, not the plain
 * 60-char Str::random.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'portal_token_expires_at')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->timestamp('portal_token_expires_at')->nullable()->after('portal_token');
            });
        }

        // Invalidate every existing plaintext token. They can't be matched
        // by the new hash-based lookup, so leaving them in the DB serves
        // no purpose and just leaks token material.
        DB::table('clients')
            ->whereNotNull('portal_token')
            ->update([
                'portal_token'             => null,
                'portal_token_expires_at'  => null,
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'portal_token_expires_at')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('portal_token_expires_at');
            });
        }
        // Intentionally NOT restoring plaintext tokens — they're gone.
    }
};
