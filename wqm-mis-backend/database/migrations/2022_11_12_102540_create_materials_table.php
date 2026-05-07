<?php

use App\Enums\MaterialStatusEnum;
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
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->text('name')->fulltext();
            $table->decimal('quantity');
            $table->decimal('available_quantity');
            $table->string('unit');
            $table->decimal('threshold');
            $table->boolean('is_active')->default(true);
            $table->string('status')->comment(implode(',', MaterialStatusEnum::values()));
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
        Schema::dropIfExists('materials');
    }
};
