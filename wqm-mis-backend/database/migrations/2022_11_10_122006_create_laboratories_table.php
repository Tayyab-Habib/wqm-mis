<?php

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
        Schema::create('laboratories', function (Blueprint $table) {
            $table->id();
            $table->text('name')->fullText();
            $table->string('latitude');
            $table->string('longitude');
            $table->string('phone');
            $table->string('fax');
            $table->string('email')->unique();
            $table->text('address')->fullText();
            $table->foreignId('focal_person_id')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('union_council_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('tehsil_id')->nullable()->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('district_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('province_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
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
        Schema::dropIfExists('laboratories');
    }
};
