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
        // Idempotent: in some environments these columns were added manually
        // before this migration was recorded, so only add what's missing.
        Schema::table('water_samples', function (Blueprint $table) {
            $hasHub = Schema::hasColumn('water_samples', 'hub_lab_id');
            $hasSub = Schema::hasColumn('water_samples', 'sub_division_id');
            if (!$hasHub) {
                $table->foreignId('hub_lab_id')->nullable()->after('phed_division_id')
                    ->constrained()->restrictOnUpdate()->restrictOnDelete();
            }
            if (!$hasSub) {
                $table->foreignId('sub_division_id')->nullable()->after($hasHub ? 'hub_lab_id' : 'phed_division_id')
                    ->constrained()->restrictOnUpdate()->restrictOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('water_samples', function (Blueprint $table) {
            if (Schema::hasColumn('water_samples', 'hub_lab_id')) {
                $table->dropForeign(['hub_lab_id']);
                $table->dropColumn('hub_lab_id');
            }
            if (Schema::hasColumn('water_samples', 'sub_division_id')) {
                $table->dropForeign(['sub_division_id']);
                $table->dropColumn('sub_division_id');
            }
        });
    }
};
