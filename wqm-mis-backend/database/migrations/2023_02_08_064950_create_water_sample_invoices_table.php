<?php

use App\Enums\WaterSampleInvoiceStatusEnum;
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
        Schema::create('water_sample_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_sample_id')->constrained('water_samples')->restrictOnUpdate()->restrictOnDelete();
            $table->morphs('invoiceable');
            $table->decimal('price');
            $table->decimal('paid')->default(0);
            $table->decimal('balance')->default(0);
            $table->decimal('net_amount')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->string('status')->default(WaterSampleInvoiceStatusEnum::PENDING->value)->comment(implode(',', WaterSampleInvoiceStatusEnum::values()))->nullable();
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
        Schema::dropIfExists('water_sample_invoices');
    }
};
