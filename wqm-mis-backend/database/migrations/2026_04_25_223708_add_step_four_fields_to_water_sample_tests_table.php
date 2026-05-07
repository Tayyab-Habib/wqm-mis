<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('water_sample_tests', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('analyzed_at');
            $table->string('complaint')->nullable()->comment('Reason For Testing')->after('source_type');
            $table->string('desired_test')->nullable()->after('complaint');
            $table->string('sample_status')->nullable()->comment('New or M&R')->after('desired_test');
            $table->boolean('on_demand_test')->default(false)->after('sample_status');
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
            $table->dropColumn(['source_type', 'complaint', 'desired_test', 'sample_status', 'on_demand_test']);
        });
    }
};
