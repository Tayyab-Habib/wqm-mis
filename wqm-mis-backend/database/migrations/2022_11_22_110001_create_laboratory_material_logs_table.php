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
        Schema::create('laboratory_material_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('laboratory_material_id')->constrained('laboratory_materials')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('material_log_id')->nullable()->constrained('material_logs')->restrictOnUpdate()->restrictOnDelete();
            $table->date('date_of_expiry')->nullable();
            $table->string('quantity');
            $table->string('unit');
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
        Schema::dropIfExists('laboratory_material_logs');
    }
};
