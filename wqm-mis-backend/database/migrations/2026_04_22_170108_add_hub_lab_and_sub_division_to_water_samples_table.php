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
            $table->after('phed_division_id', function ($table) {
                if (! Schema::hasColumn('water_samples', 'hub_lab_id')) {
                    $table->foreignId('hub_lab_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
                }
                if (! Schema::hasColumn('water_samples', 'sub_division_id')) {
                    $table->foreignId('sub_division_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
                }
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
            $table->dropForeign(['hub_lab_id']);
            $table->dropColumn('hub_lab_id');
            $table->dropForeign(['sub_division_id']);
            $table->dropColumn('sub_division_id');
        });
    }
};
