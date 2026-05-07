<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_maintenance_schedule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_ms_id')->constrained('asset_maintenance_schedules')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('laboratory_asset_id')->constrained('laboratory_assets')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('laboratory_id')->constrained('laboratories')->restrictOnUpdate()->restrictOnDelete();
            $table->date('scheduled_at');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asset_maintenance_schedule_logs');
    }
};
