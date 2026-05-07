<?php

use App\Enums\ComplaintStatusEnum;
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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('complaint_type_id')->constrained('complaint_types')->restrictOnUpdate()->restrictOnDelete();
            $table->text('description');
            $table->string('title');
            $table->date('date_of_closing')->nullable();
            $table->string('status')->default(ComplaintStatusEnum::PENDING->value)->nullable()->comment(implode(',', ComplaintStatusEnum::values()));
            $table->string('file')->nullable();
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
        Schema::dropIfExists('complaints');
    }
};
