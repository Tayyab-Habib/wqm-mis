<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-lab monthly entries for the 4 KPI Framework metrics that aren't
 * derivable from operational tables:
 *   KPI-001 Inter-lab Comparison (PT)
 *   KPI-007 Staff Training Compliance
 *   KPI-008 SOP Compliance
 *   KPI-009 Data Verification
 *
 * Schema is intentionally generic (kpi_code string) so future manual KPIs
 * can reuse the same table without another migration.
 *
 * Period is a string 'YYYY-MM' (e.g. '2026-05'). One row per
 * (lab, kpi_code, period) — admin overwrites via upsert.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_lab_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_id')->constrained('laboratories')->cascadeOnDelete();
            $table->string('kpi_code', 16);                     // e.g. 'KPI-007'
            $table->string('period', 7);                        // 'YYYY-MM'
            $table->unsignedInteger('numerator')->default(0);   // e.g. trained staff
            $table->unsignedInteger('denominator')->default(0); // e.g. total staff
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['laboratory_id', 'kpi_code', 'period'], 'kpi_lab_periods_unique');
            $table->index(['kpi_code', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_lab_periods');
    }
};
