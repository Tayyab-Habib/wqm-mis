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
        Schema::create('water_sample_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_sample_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('test_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('analyst_id')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->string('analysis_result')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['water_sample_id', 'test_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('water_sample_details');
    }
};
