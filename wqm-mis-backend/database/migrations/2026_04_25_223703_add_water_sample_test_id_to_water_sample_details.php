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
        Schema::table('water_sample_details', function (Blueprint $table) {
            $table->foreignId('water_sample_test_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('water_sample_details', function (Blueprint $table) {
            $table->dropForeign(['water_sample_test_id']);
            $table->dropColumn('water_sample_test_id');
        });
    }
};
