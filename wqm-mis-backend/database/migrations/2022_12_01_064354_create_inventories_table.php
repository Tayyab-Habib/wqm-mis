<?php

use App\Enums\InventoryStatusEnum;
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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->uuid('slug')->nullable();
            $table->foreignId('laboratory_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('modified_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('status')->default(InventoryStatusEnum::PENDING->value)->comment(implode(',', InventoryStatusEnum::values()));
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
        Schema::dropIfExists('inventories');
    }
};
