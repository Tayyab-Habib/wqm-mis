<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('equipment_calibration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_asset_id')
                  ->constrained('laboratory_assets')
                  ->restrictOnUpdate()
                  ->restrictOnDelete();
            $table->date('calibration_date');
            $table->string('calibrated_by');
            $table->string('result'); // Pass / Conditional Pass / Fail
            $table->string('certificate_ref')->nullable();
            $table->string('standard_used')->nullable();
            $table->date('next_due_date')->nullable(); // auto-computed from calibration_cycle — the missing field
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('equipment_calibration_logs');
    }
};
