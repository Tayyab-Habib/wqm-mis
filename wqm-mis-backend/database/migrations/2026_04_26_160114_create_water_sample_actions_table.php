<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('water_sample_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_sample_id')->constrained('water_samples')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('round')->default(0);
            $table->string('action_type');
            $table->text('details')->nullable();
            $table->json('attachments')->nullable();
            $table->date('action_date');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('water_sample_actions');
    }
};
