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
        Schema::create('water_scheme_schedule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wss_schedule_id')->constrained('water_scheme_schedules')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('water_scheme_id')->constrained('water_schemes')->restrictOnUpdate()->restrictOnDelete();
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
        Schema::dropIfExists('water_scheme_schedule_logs');
    }
};
