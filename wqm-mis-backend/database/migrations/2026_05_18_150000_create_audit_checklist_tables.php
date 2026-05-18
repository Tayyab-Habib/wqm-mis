<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit / SOP Inspection Checklist (KPI-008 source).
 *
 * Per SRS §3.4 the data source is "Audit / Inspection checklist module".
 * Model:
 *   audit_checklist_items     — admin-defined master list of SOP checklist
 *                               questions (single global checklist, items
 *                               can be activated/deactivated)
 *   audit_inspections         — one row per visit: lab + inspector + date
 *   audit_inspection_answers  — one row per (inspection × checklist item)
 *                               with pass / fail / na
 *
 * KPI per lab over period (latest inspection wins):
 *   pass_count / (pass_count + fail_count) × 100
 *   N/A answers are excluded from the denominator (industry-standard
 *   checklist scoring — items that don't apply shouldn't drag the score).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('position')->default(0);
            $table->string('question', 500);
            $table->string('category', 100)->nullable();      // optional grouping
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'position']);
        });

        Schema::create('audit_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_id')->constrained('laboratories')->cascadeOnDelete();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('inspection_date');
            $table->enum('status', ['draft', 'submitted'])->default('submitted');
            $table->text('notes')->nullable();
            $table->string('evidence_file', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['laboratory_id', 'inspection_date']);
        });

        Schema::create('audit_inspection_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_inspection_id')->constrained('audit_inspections')->cascadeOnDelete();
            $table->foreignId('audit_checklist_item_id')->constrained('audit_checklist_items')->restrictOnDelete();
            $table->enum('answer', ['pass', 'fail', 'na'])->default('pass');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['audit_inspection_id', 'audit_checklist_item_id'], 'audit_answer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_inspection_answers');
        Schema::dropIfExists('audit_inspections');
        Schema::dropIfExists('audit_checklist_items');
    }
};
