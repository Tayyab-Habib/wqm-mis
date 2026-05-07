<?php

use App\Enums\AssetMaintenanceStatusEnum;
use App\Enums\StatusEnum;
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
        Schema::create('asset_maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_asset_id')->constrained('laboratory_assets')->restrictOnUpdate()->restrictOnDelete();
            $table->string('day_of_month');
            $table->string('frequency');
            $table->string('is_recurring');
            $table->string('status')->default(StatusEnum::ACTIVE->value)->nullable()->comment(implode(',', StatusEnum::values()));
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
        Schema::dropIfExists('asset_maintenance_schedules');
    }
};
