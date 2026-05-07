<?php

use App\Enums\EmploymentStatusEnum;
use App\Enums\GenderEnum;
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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('image')->nullable();
            $table->string('phone');
            $table->string('gender')->comment(implode(',', GenderEnum::values()));
            $table->date('date_of_birth');
            $table->date('date_of_joining')->nullable();
            $table->string('employee_status')->comment(implode(',', EmploymentStatusEnum::values()));
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->text('career_background');
            $table->text('educational_background');
            $table->integer('basic_pay_scale');
            $table->softDeletes();
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
};
