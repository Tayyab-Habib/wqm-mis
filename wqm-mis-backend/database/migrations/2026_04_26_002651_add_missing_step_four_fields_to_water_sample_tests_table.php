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
        Schema::table('water_sample_tests', function (Blueprint $table) {
            $table->string('source_sub_type')->nullable();
            $table->string('complaint_by_other')->nullable();
            $table->text('on_demand_tests')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('water_sample_tests', function (Blueprint $table) {
            $table->dropColumn(['source_sub_type', 'complaint_by_other', 'on_demand_tests']);
        });
    }
};
