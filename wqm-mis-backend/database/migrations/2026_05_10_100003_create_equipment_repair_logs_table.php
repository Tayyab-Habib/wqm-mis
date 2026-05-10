<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('equipment_repair_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_asset_id')
                  ->constrained('laboratory_assets')
                  ->restrictOnUpdate()
                  ->restrictOnDelete();
            $table->date('fault_date');
            $table->string('fault_description');
            $table->string('repair_status'); // Reported / Under Repair / Resolved / Beyond Repair
            $table->string('technician')->nullable();
            $table->date('resolved_date')->nullable();
            $table->decimal('repair_cost', 12, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('equipment_repair_logs');
    }
};
