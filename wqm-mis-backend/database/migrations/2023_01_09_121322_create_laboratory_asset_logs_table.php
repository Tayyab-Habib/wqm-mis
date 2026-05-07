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
        Schema::create('laboratory_asset_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_asset_id')->constrained('laboratory_assets')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('asset_log_id')->nullable()->constrained('asset_logs')->restrictOnUpdate()->restrictOnDelete();
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
        Schema::dropIfExists('laboratory_asset_logs');
    }
};
