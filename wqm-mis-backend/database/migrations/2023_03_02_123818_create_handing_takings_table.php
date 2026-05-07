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
        Schema::create('handing_takings', function (Blueprint $table) {
            $table->id();
            $table->morphs('stockable');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->text('description');
            $table->decimal('quantity');
            $table->string('unit');
            $table->foreignId('assigned_to')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('laboratory_id')->constrained('laboratories')->restrictOnUpdate()->restrictOnDelete();
            $table->softDeletes();
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
        Schema::dropIfExists('handing_takings');
    }
};
