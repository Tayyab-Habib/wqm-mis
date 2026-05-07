<?php

use App\Enums\DiaryDispatchEnum;
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
        Schema::create('diary_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('person_name');
            $table->date('date_on_letter');
            $table->date('receival_date')->nullable();
            $table->string('attachment_name');
            $table->string('attachment');
            $table->string('type')->comment(implode(',', DiaryDispatchEnum::values()));
            $table->foreignId('designation_id')->nullable()->constrained('designations')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('laboratory_id')->nullable()->constrained('laboratories')->restrictOnUpdate()->restrictOnDelete();
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
        Schema::dropIfExists('diary_dispatches');
    }
};
