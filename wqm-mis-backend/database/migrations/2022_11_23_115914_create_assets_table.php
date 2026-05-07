<?php

use App\Enums\AssetStatusEnum;
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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->text('name')->fulltext();
            $table->decimal('quantity');
            $table->string('unit');
            $table->string('date_of_expiry')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->comment(implode(',', AssetStatusEnum::values()));
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
        Schema::dropIfExists('assets');
    }
};
