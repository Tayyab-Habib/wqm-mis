<?php

use App\Enums\ResponsibleTypeEnum;
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
        Schema::create('issue_responsible', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('responsible_id')->constrained('users')->restrictOnUpdate()->restrictOnDelete();
            $table->string('responsible_type')->comment(implode(',', ResponsibleTypeEnum::values()));
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
        Schema::dropIfExists('issue_responsible');
    }
};
