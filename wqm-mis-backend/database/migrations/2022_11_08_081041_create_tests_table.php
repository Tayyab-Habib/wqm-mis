<?php

use App\Enums\TestTypeEnum;
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
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->string('type')->comment(implode(',', TestTypeEnum::values()));
            $table->string('water_quality_parameter');
            $table->string('unit')->nullable();
            $table->string('detectable_limit')->nullable();
            $table->string('reference_method')->nullable();
            $table->string('permissible_limits')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->boolean('is_mandatory')->default(true);
            $table->decimal('rate')->default(0);
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
        Schema::dropIfExists('tests');
    }
};
