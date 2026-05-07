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
        Schema::table('water_samples', function (Blueprint $table) {
            $table->tinyInteger('current_status')->index()->nullable()->after('remarks');
            $table->tinyInteger('current_round')->default(0)->after('current_status');
            $table->boolean('is_closed')->default(false)->after('current_round');
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
            $table->dropColumn(['current_status', 'current_round', 'is_closed']);
        });
    }
};
