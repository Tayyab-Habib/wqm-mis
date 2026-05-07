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
            // Add a plain index so the foreign key is satisfied
            $table->index('water_sample_id');

            // Drop old constraint
            $table->dropUnique('water_sample_details_water_sample_id_test_id_unique');
            
            // Add new constraint
            $table->unique(['water_sample_test_id', 'test_id']);
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
            $table->dropUnique(['water_sample_test_id', 'test_id']);
            $table->unique(['water_sample_id', 'test_id']);
        });
    }
};
