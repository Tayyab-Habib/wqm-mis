<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the XEN -> Secretary transfer audit trail to water_samples.
 *
 * When a sample reaches Persistent Unfit (R2+ failure), the XEN can hand it
 * off to the Secretary for a Fate Decision. These columns capture *when* and
 * *who* did the transfer plus an optional note that the Secretary sees.
 *
 * Idempotent — only adds columns that aren't already present.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_samples', function (Blueprint $table) {
            if (!Schema::hasColumn('water_samples', 'transferred_to_secretary_at')) {
                $table->timestamp('transferred_to_secretary_at')->nullable()->after('is_closed');
            }
            if (!Schema::hasColumn('water_samples', 'transferred_to_secretary_by')) {
                $table->unsignedBigInteger('transferred_to_secretary_by')->nullable()->after('transferred_to_secretary_at');
            }
            if (!Schema::hasColumn('water_samples', 'transferred_to_secretary_remarks')) {
                $table->text('transferred_to_secretary_remarks')->nullable()->after('transferred_to_secretary_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('water_samples', function (Blueprint $table) {
            foreach (['transferred_to_secretary_at', 'transferred_to_secretary_by', 'transferred_to_secretary_remarks'] as $col) {
                if (Schema::hasColumn('water_samples', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
