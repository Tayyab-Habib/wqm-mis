<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Training Register (KPI-007 source).
 *
 * Per SRS §3.4 the data source is "Training register (filled by RO/SRO)".
 * Each row = one training event for one staff member. Validity is 12 months
 * by default (industry standard for SOP refresher cycles). KPI rolls up as:
 *   per lab: distinct(staff with valid training) / total lab staff × 100
 *
 * Staff is referenced by user_id (FK to users) when the trainee is a
 * system user. A free-text `staff_name` is kept as a fallback for
 * back-dating historical trainings of staff who left, or external
 * contractors who never had a login.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_id')->constrained('laboratories')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('staff_name', 255);                 // mirrors user name at time of entry
            $table->string('training_topic', 255);             // e.g. "Sample Collection SOP"
            $table->date('training_date');
            $table->date('valid_until');                       // default training_date + 12 mo (set by controller)
            $table->string('evidence_file', 500)->nullable();  // PDF certificate path
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['laboratory_id', 'training_date']);
            $table->index(['user_id', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_trainings');
    }
};
