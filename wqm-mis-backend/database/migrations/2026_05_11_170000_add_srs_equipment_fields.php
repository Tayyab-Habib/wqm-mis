<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS §2.7-3 Equipment Register required fields not yet present:
 *
 * Master (laboratory_assets):
 *   - Serial No.
 *   - Warranty Expiry
 *   - Purchase Value (lab-level)
 *
 * Calibration Log (equipment_calibration_logs):
 *   - certificate_attachment (file path)
 *
 * Repair Log (equipment_repair_logs):
 *   - reported_by (who reported the fault)
 *   - attachment (file path)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('laboratory_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('laboratory_assets', 'serial_number')) {
                $table->string('serial_number', 128)->nullable()->after('make_model');
            }
            if (!Schema::hasColumn('laboratory_assets', 'warranty_expiry')) {
                $table->date('warranty_expiry')->nullable()->after('purchased_at');
            }
            if (!Schema::hasColumn('laboratory_assets', 'purchase_value')) {
                $table->decimal('purchase_value', 14, 2)->nullable()->after('warranty_expiry');
            }
        });

        Schema::table('equipment_calibration_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('equipment_calibration_logs', 'certificate_attachment')) {
                $table->string('certificate_attachment', 500)->nullable()->after('certificate_ref');
            }
        });

        Schema::table('equipment_repair_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('equipment_repair_logs', 'reported_by')) {
                $table->string('reported_by', 255)->nullable()->after('fault_description');
            }
            if (!Schema::hasColumn('equipment_repair_logs', 'attachment')) {
                $table->string('attachment', 500)->nullable()->after('repair_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_assets', function (Blueprint $table) {
            $table->dropColumn(['serial_number', 'warranty_expiry', 'purchase_value']);
        });
        Schema::table('equipment_calibration_logs', function (Blueprint $table) {
            $table->dropColumn(['certificate_attachment']);
        });
        Schema::table('equipment_repair_logs', function (Blueprint $table) {
            $table->dropColumn(['reported_by', 'attachment']);
        });
    }
};
