<?php

use App\Enums\FrequencyEnum;
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
        Schema::create('water_scheme_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_scheme_id')->constrained('water_schemes')->restrictOnUpdate()->restrictOnDelete();
            $table->string('day_of_month');
            $table->string('frequency')->comment(implode(',', FrequencyEnum::array()));
            $table->boolean('is_recurring');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
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
        Schema::dropIfExists('water_scheme_schedules');
    }
};
