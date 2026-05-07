<?php

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
        Schema::table('water_scheme_schedules', function (Blueprint $table) {
            $table->after('is_recurring', function (Blueprint $table){
                $table->foreignId('laboratory_id')->constrained('laboratories')->restrictOnUpdate()->restrictOnDelete();
                $table->string('status')->default(StatusEnum::ACTIVE->value)->comment(implode(', ', StatusEnum::array()));
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('water_scheme_schedules', function (Blueprint $table) {
            $table->dropColumn(['status', 'laboratory_id']);
        });
    }
};
