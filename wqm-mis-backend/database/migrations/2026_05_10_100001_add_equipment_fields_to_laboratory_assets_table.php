<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('laboratory_assets', function (Blueprint $table) {
            $table->string('make_model')->nullable()->after('status');
            $table->string('calibration_cycle')->nullable()->after('make_model');
            $table->date('next_calibration_date')->nullable()->after('calibration_cycle');
            $table->date('purchased_at')->nullable()->after('next_calibration_date');
        });
    }

    public function down()
    {
        Schema::table('laboratory_assets', function (Blueprint $table) {
            $table->dropColumn(['make_model', 'calibration_cycle', 'next_calibration_date', 'purchased_at']);
        });
    }
};
