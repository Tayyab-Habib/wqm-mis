<?php

use App\Enums\AssetMaintenanceTypeEnum;
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
        Schema::table('asset_maintenance_schedules', function (Blueprint $table) {
            $table->string('type')->after('is_recurring')->comment(implode(', ', AssetMaintenanceTypeEnum::array()));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('asset_maintenance_schedules', function (Blueprint $table) {
            $table->dropColumn(['type']);
        });
    }
};
