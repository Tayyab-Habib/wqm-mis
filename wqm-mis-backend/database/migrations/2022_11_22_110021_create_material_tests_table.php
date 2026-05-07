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
        Schema::create('material_tests', function (Blueprint $table) {
            $table->id();
//            $table->foreignId('material_id')->constrained('materials')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('test_id')->constrained('tests')->restrictOnUpdate()->restrictOnDelete();
            $table->string('quantity');
            $table->string('unit');
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
        Schema::dropIfExists('material_tests');
    }
};
