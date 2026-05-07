<?php

use App\Enums\PowerInputEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('water_schemes', function (Blueprint $table) {
            $table->id();
            $table->text('name')->fulltext();
            $table->string('latitude');
            $table->uuid('slug')->nullable();
            $table->string('longitude');
            $table->string('address');
            $table->boolean('is_active')->default(true);
            $table->string('source_type')->nullable();
            $table->string('years_of_installation')->nullable();
            $table->string('mode')->nullable();
            $table->string('operation')->nullable();
            $table->string('type_of_machine')->nullable();
            $table->string('horse_power_motor')->nullable();
            $table->string('power_input')->nullable()->comment(implode(',', PowerInputEnum::values()));
            $table->string('storage')->nullable();
            $table->string('capacity')->nullable();
            $table->string('depth')->nullable();
            $table->string('chamber')->nullable();
            $table->string('pipe_type')->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('union_council_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnUpdate();
            $table->foreignId('tehsil_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnUpdate();
            $table->foreignId('district_id')->constrained()->restrictOnUpdate()->restrictOnUpdate();
            $table->foreignId('division_id')->constrained()->restrictOnUpdate()->restrictOnUpdate();
            $table->foreignId('province_id')->constrained()->restrictOnUpdate()->restrictOnUpdate();
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
        Schema::dropIfExists('water_schemes');
    }
};
