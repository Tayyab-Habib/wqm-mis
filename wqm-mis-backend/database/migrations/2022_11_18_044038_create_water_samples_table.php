<?php

use App\Enums\CollectedByEnum;
use App\Enums\CollectedInEnum;
use App\Enums\DesiredTestEnum;
use App\Enums\ReasonForTestingEnum;
use App\Enums\SamplingPointEnum;
use App\Enums\SourceTypeEnum;
use App\Enums\TestFrequencyEnum;
use App\Enums\WaterSampleResultEnum;
use App\Enums\WaterSampleStatusEnum;
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
        Schema::create('water_samples', function (Blueprint $table) {
            $table->id();
            $table->string('test_type')->comment(implode(',', TestFrequencyEnum::values()));
            $table->uuid('slug')->nullable();
            $table->longText('qr_code')->nullable();
            $table->foreignId('water_scheme_id')->constrained('water_schemes')->restrictOnUpdate()->restrictOnDelete();
            $table->string('source_type')->comment(implode(',', SourceTypeEnum::values()));
            $table->string('sampling_point')->comment(implode(',', SamplingPointEnum::values()));
            $table->string('collected_by')->comment(implode(',', CollectedByEnum::values()));
            $table->string('latitude');
            $table->string('longitude');
            $table->string('status')->comment(implode(',', WaterSampleStatusEnum::values()));
            $table->integer('temperature_in_celsius');
            $table->dateTime('sampled_at');
            $table->dateTime('analyzed_at')->nullable();
            $table->dateTime('reported_at')->nullable();
            $table->string('collected_in')->comment(implode(',', CollectedInEnum::values()));
            $table->string('collected_in_other')->nullable();
            $table->string('complaint')->comment(implode(',', ReasonForTestingEnum::values()));
            $table->string('complaint_by_other')->nullable();
            $table->string('desired_test')->comment(implode(',', DesiredTestEnum::values()));
            $table->foreignId('laboratory_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('union_council_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('tehsil_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('district_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('province_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->string('result')->nullable()->comment(implode(',', WaterSampleResultEnum::values()));
            $table->morphs('collectable');
            $table->foreignId('lab_incharge_id')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('research_officer_id')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('water_samples');
    }
};
