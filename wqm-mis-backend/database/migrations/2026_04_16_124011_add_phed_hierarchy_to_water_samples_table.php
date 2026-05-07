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
        Schema::table('water_samples', function (Blueprint $table) {
            $table->after('province_id', function ($table) {
                $table->foreignId('region_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
                $table->foreignId('circle_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
                $table->foreignId('phed_division_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('water_samples', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
            $table->dropForeign(['circle_id']);
            $table->dropColumn('circle_id');
            $table->dropForeign(['phed_division_id']);
            $table->dropColumn('phed_division_id');
        });
    }
};
