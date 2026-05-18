<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proficiency Testing (PT) Module — KPI-001 source.
 *
 * Per SRS §3.4 the data source is "PT Module (SA reference vs lab result)".
 * Workflow:
 *   1. SA creates a PT round with one or more parameter items, each carrying
 *      a reference value + tolerance %.
 *   2. Selected labs receive the PT sample, run it, submit one reading per
 *      item.
 *   3. System auto-computes pass/fail per result based on tolerance.
 *   4. KPI per lab over period = passed_results / submitted_results × 100.
 *
 * Tables:
 *   pt_rounds              — header (date, due, status, notes)
 *   pt_round_items         — parameter rows (test_id, ref, tolerance %)
 *   pt_round_participants  — which labs are in this round (status, submitted_at)
 *   pt_round_results       — one reading per (participant × item)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pt_rounds', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();              // e.g. "PT-2026-05"
            $table->string('name', 255);
            $table->date('round_date');                        // sample distribution date
            $table->date('due_date');                          // labs must submit by this date
            $table->enum('status', ['draft', 'open', 'closed'])->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('round_date');
        });

        Schema::create('pt_round_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pt_round_id')->constrained('pt_rounds')->cascadeOnDelete();
            $table->foreignId('test_id')->constrained('tests')->restrictOnDelete();
            $table->decimal('reference_value', 14, 4);          // SA-provided expected value
            $table->decimal('tolerance_pct', 5, 2)->default(15.00); // ±N% from reference
            $table->string('unit', 32)->nullable();             // snapshot at round-creation time
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['pt_round_id', 'test_id'], 'pt_round_items_unique');
        });

        Schema::create('pt_round_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pt_round_id')->constrained('pt_rounds')->cascadeOnDelete();
            $table->foreignId('laboratory_id')->constrained('laboratories')->restrictOnDelete();
            $table->enum('status', ['pending', 'submitted'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['pt_round_id', 'laboratory_id'], 'pt_round_participants_unique');
        });

        Schema::create('pt_round_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pt_round_participant_id')->constrained('pt_round_participants')->cascadeOnDelete();
            $table->foreignId('pt_round_item_id')->constrained('pt_round_items')->cascadeOnDelete();
            $table->decimal('submitted_value', 14, 4);
            $table->decimal('deviation_pct', 8, 4)->nullable();  // |submitted - ref| / ref × 100
            $table->boolean('passed')->default(false);            // |deviation| <= tolerance
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['pt_round_participant_id', 'pt_round_item_id'], 'pt_round_results_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pt_round_results');
        Schema::dropIfExists('pt_round_participants');
        Schema::dropIfExists('pt_round_items');
        Schema::dropIfExists('pt_rounds');
    }
};
