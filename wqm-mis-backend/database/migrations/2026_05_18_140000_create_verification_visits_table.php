<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verification Visit Log (KPI-009 source).
 *
 * Per SRS §3.4 the data source is "Technical head visit / verification log".
 * Each row = one verification visit; technical head re-tests a random sample
 * of the lab's reported results, records how many matched. KPI formula
 * (per lab over period):
 *   sum(samples_matched) / sum(samples_verified) × 100
 *
 * A companion `verification_visit_samples` table holds the individual
 * sample IDs that were re-verified — useful for the audit trail "show me
 * which 2 samples the lab got wrong in last month's visit".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('verification_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_id')->constrained('laboratories')->cascadeOnDelete();
            $table->foreignId('technical_head_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('visit_date');
            $table->unsignedInteger('samples_verified')->default(0);
            $table->unsignedInteger('samples_matched')->default(0);
            $table->text('observations')->nullable();
            $table->string('evidence_file', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['laboratory_id', 'visit_date']);
        });

        Schema::create('verification_visit_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_visit_id')->constrained('verification_visits')->cascadeOnDelete();
            $table->foreignId('water_sample_id')->nullable()->constrained('water_samples')->nullOnDelete();
            $table->string('sample_slug', 64)->nullable();   // snapshot at time of visit
            $table->boolean('matched')->default(true);        // did re-test match the lab's reported result?
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('verification_visit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_visit_samples');
        Schema::dropIfExists('verification_visits');
    }
};
