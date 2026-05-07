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
        Schema::create('water_sample_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_sample_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('round')->comment('0 = Fresh, 1-3 = Retests');
            $table->string('sampling_point');
            $table->string('collected_by');
            $table->string('collected_in');
            $table->string('collected_in_other')->nullable();
            $table->decimal('temperature_in_celsius', 8, 2)->nullable();
            $table->dateTime('sampled_at');
            $table->dateTime('reported_at')->nullable();
            $table->dateTime('analyzed_at')->nullable();
            $table->tinyInteger('status')->default(0)->comment('0=Pending, 1=Completed');
            $table->tinyInteger('result')->nullable()->comment('1=Fit, 2=Unfit');
            $table->text('remarks')->nullable();
            $table->foreignId('lab_incharge_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('research_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_final')->default(false);
            $table->timestamps();

            $table->unique(['water_sample_id', 'round']);
            $table->index(['water_sample_id', 'round']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('water_sample_tests');
    }
};
