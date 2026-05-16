<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-03 — Backfill `sbp_submissions` schema.
 *
 * The original `create_sbp_submissions_table` migration (2026_05_09_132936)
 * and its three follow-ups created only `id` + `timestamps`. The dev DB has
 * the columns because they were ALTERed manually, but a `migrate:fresh` on
 * a clean install produces an unusable table. This migration is idempotent
 * (uses `Schema::hasColumn`) so it is safe to run on environments where the
 * columns already exist and will populate them on fresh installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sbp_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('sbp_submissions', 'submission_slug')) {
                $table->string('submission_slug')->nullable()->after('id');
            }
            if (!Schema::hasColumn('sbp_submissions', 'laboratory_id')) {
                $table->unsignedBigInteger('laboratory_id')->nullable()->after('submission_slug');
            }
            if (!Schema::hasColumn('sbp_submissions', 'period_from')) {
                $table->date('period_from')->nullable();
            }
            if (!Schema::hasColumn('sbp_submissions', 'period_to')) {
                $table->date('period_to')->nullable();
            }
            if (!Schema::hasColumn('sbp_submissions', 'amount')) {
                $table->decimal('amount', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('sbp_submissions', 'challan_no')) {
                $table->string('challan_no')->nullable();
            }
            if (!Schema::hasColumn('sbp_submissions', 'deposit_date')) {
                $table->date('deposit_date')->nullable();
            }
            if (!Schema::hasColumn('sbp_submissions', 'submitted_by_id')) {
                $table->unsignedBigInteger('submitted_by_id')->nullable();
            }
            if (!Schema::hasColumn('sbp_submissions', 'submitted_by_name')) {
                $table->string('submitted_by_name')->nullable();
            }
            if (!Schema::hasColumn('sbp_submissions', 'status')) {
                $table->string('status')->default('submitted');
            }
            if (!Schema::hasColumn('sbp_submissions', 'remarks')) {
                $table->text('remarks')->nullable();
            }
            if (!Schema::hasColumn('sbp_submissions', 'attachment_path')) {
                $table->string('attachment_path')->nullable();
            }
            if (!Schema::hasColumn('sbp_submissions', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
            if (!Schema::hasColumn('sbp_submissions', 'verified_by_id')) {
                $table->unsignedBigInteger('verified_by_id')->nullable();
            }
            if (!Schema::hasColumn('sbp_submissions', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        // Intentionally NOT dropping columns on rollback: the originally
        // missing schema is what we're recovering from. A `down` here would
        // recreate the broken state.
    }
};
