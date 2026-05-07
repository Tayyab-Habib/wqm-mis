<?php

use App\Enums\InventoryDetailStatusEnum;
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
        Schema::create('inventory_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->morphs('inventoryable');
            $table->decimal('quantity');
            $table->decimal('approved_quantity')->default(0);
            $table->string('unit');
            $table->boolean('is_received')->default(false);
            $table->date('received_at')->nullable();
            $table->string('status')->default(InventoryDetailStatusEnum::PENDING->value)->nullable()->comment(implode(',', InventoryDetailStatusEnum::values()));
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
        Schema::dropIfExists('inventory_details');
    }
};
