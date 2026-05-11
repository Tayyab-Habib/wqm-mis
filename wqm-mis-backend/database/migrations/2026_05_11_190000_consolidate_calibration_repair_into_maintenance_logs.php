<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidate equipment_calibration_logs + equipment_repair_logs into the
 * unified asset_maintenance_logs table. Calibration entries are stored with
 * type='calibration', repair entries with type='repair'. Frontend API
 * contract is preserved via column aliases in the controllers.
 *
 * Why asset_maintenance_logs (not laboratory_asset_logs):
 *   - Calibration and repair ARE maintenance activities, semantically.
 *   - asset_maintenance_logs is empty (0 rows) → safe destination.
 *   - laboratory_asset_logs is for stock-flow events (IN/OUT/transfer).
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. Extend asset_maintenance_logs to hold calibration/repair detail.
        // Relax asset_maintenance_schedule_id + user_id to NULL via raw SQL
        // (doctrine/dbal ->change() isn't usable on this Laravel/Doctrine combo).
        DB::statement('ALTER TABLE asset_maintenance_logs MODIFY asset_maintenance_schedule_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE asset_maintenance_logs MODIFY user_id BIGINT UNSIGNED NULL');
        // status is NOT NULL with no default — give it a default so future inserts
        // that don't specify a status (e.g. migrated calibration/repair events)
        // don't violate the constraint.
        DB::statement("ALTER TABLE asset_maintenance_logs MODIFY status VARCHAR(255) NOT NULL DEFAULT 'completed'");
        // comment is NOT NULL but should accept empty/migrated rows; relax to NULL.
        DB::statement('ALTER TABLE asset_maintenance_logs MODIFY comment VARCHAR(1000) NULL');

        // Idempotent column additions — skip any that already exist (e.g. when
        // a prior partial run added them before the INSERT step blew up).
        Schema::table('asset_maintenance_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('asset_maintenance_logs', 'laboratory_asset_id')) {
                $table->foreignId('laboratory_asset_id')->nullable()->after('asset_maintenance_schedule_id')
                    ->constrained('laboratory_assets')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('asset_maintenance_logs', 'type'))           $table->string('type', 32)->nullable()->after('laboratory_asset_id');
            if (!Schema::hasColumn('asset_maintenance_logs', 'event_date'))     $table->date('event_date')->nullable()->after('type');
            if (!Schema::hasColumn('asset_maintenance_logs', 'result'))         $table->string('result', 255)->nullable()->after('event_date');
            if (!Schema::hasColumn('asset_maintenance_logs', 'next_due_date'))  $table->date('next_due_date')->nullable()->after('result');
            if (!Schema::hasColumn('asset_maintenance_logs', 'description'))    $table->text('description')->nullable()->after('next_due_date');
            if (!Schema::hasColumn('asset_maintenance_logs', 'reported_by'))    $table->string('reported_by', 255)->nullable()->after('description');
            if (!Schema::hasColumn('asset_maintenance_logs', 'resolved_date'))  $table->date('resolved_date')->nullable()->after('reported_by');
            if (!Schema::hasColumn('asset_maintenance_logs', 'cost'))           $table->decimal('cost', 12, 2)->nullable()->after('resolved_date');
            if (!Schema::hasColumn('asset_maintenance_logs', 'performer'))      $table->string('performer', 255)->nullable()->after('cost');
            if (!Schema::hasColumn('asset_maintenance_logs', 'ref_number'))     $table->string('ref_number', 255)->nullable()->after('performer');
            if (!Schema::hasColumn('asset_maintenance_logs', 'standard_used'))  $table->string('standard_used', 255)->nullable()->after('ref_number');
        });

        // 2. Copy calibration data → asset_maintenance_logs (type='calibration').
        //   calibration_date       -> event_date
        //   calibrated_by          -> performer
        //   result                 -> result
        //   certificate_ref        -> ref_number
        //   certificate_attachment -> file (existing column)
        //   standard_used          -> standard_used
        //   next_due_date          -> next_due_date
        //   remarks                -> comment (existing column)
        if (Schema::hasTable('equipment_calibration_logs')) {
            DB::statement("
                INSERT INTO asset_maintenance_logs
                    (laboratory_asset_id, type, event_date, performer, result,
                     ref_number, file, standard_used, next_due_date, comment,
                     created_at, updated_at)
                SELECT
                    laboratory_asset_id, 'calibration', calibration_date, calibrated_by, result,
                    certificate_ref, certificate_attachment, standard_used, next_due_date, remarks,
                    created_at, updated_at
                FROM equipment_calibration_logs
            ");
        }

        // 3. Copy repair data → asset_maintenance_logs (type='repair').
        //   fault_date        -> event_date
        //   fault_description -> description
        //   reported_by       -> reported_by
        //   repair_status     -> result
        //   attachment        -> file
        //   technician        -> performer
        //   resolved_date     -> resolved_date
        //   repair_cost       -> cost
        //   remarks           -> comment
        if (Schema::hasTable('equipment_repair_logs')) {
            DB::statement("
                INSERT INTO asset_maintenance_logs
                    (laboratory_asset_id, type, event_date, description, reported_by,
                     result, file, performer, resolved_date, cost, comment,
                     created_at, updated_at)
                SELECT
                    laboratory_asset_id, 'repair', fault_date, fault_description, reported_by,
                    repair_status, attachment, technician, resolved_date, repair_cost, remarks,
                    created_at, updated_at
                FROM equipment_repair_logs
            ");
        }

        // 4. Drop the old tables.
        Schema::dropIfExists('equipment_calibration_logs');
        Schema::dropIfExists('equipment_repair_logs');
    }

    public function down(): void
    {
        // Restore the original tables (empty). Data is not moved back — disambiguating
        // calibration vs repair rows back into per-table schemas would be risky.
        Schema::create('equipment_calibration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_asset_id')->constrained()->cascadeOnDelete();
            $table->date('calibration_date');
            $table->string('calibrated_by');
            $table->string('result');
            $table->string('certificate_ref')->nullable();
            $table->string('certificate_attachment', 500)->nullable();
            $table->string('standard_used')->nullable();
            $table->date('next_due_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment_repair_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_asset_id')->constrained()->cascadeOnDelete();
            $table->date('fault_date');
            $table->string('fault_description');
            $table->string('reported_by')->nullable();
            $table->string('repair_status');
            $table->string('attachment', 500)->nullable();
            $table->string('technician')->nullable();
            $table->date('resolved_date')->nullable();
            $table->decimal('repair_cost', 12, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::table('asset_maintenance_logs', function (Blueprint $table) {
            $table->dropForeign(['laboratory_asset_id']);
            $table->dropColumn([
                'laboratory_asset_id', 'type', 'event_date', 'result', 'next_due_date',
                'description', 'reported_by', 'resolved_date', 'cost', 'performer',
                'ref_number', 'standard_used',
            ]);
            // (asset_maintenance_schedule_id nullability change is left in place — harmless.)
        });
    }
};
