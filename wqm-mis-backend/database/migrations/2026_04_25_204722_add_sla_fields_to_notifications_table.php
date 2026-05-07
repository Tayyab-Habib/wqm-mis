<?php

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
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('water_sample_id')->nullable()->constrained('water_samples')->nullOnDelete();
            $table->tinyInteger('round')->nullable();
            $table->string('role')->nullable();
            $table->tinyInteger('status')->nullable()->comment('Pending=1, ActionTaken=2, Delayed=3');
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('action_taken_at')->nullable();
            $table->string('type_key')->nullable()->comment('unfit, retest_request');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['water_sample_id']);
            $table->dropColumn([
                'water_sample_id',
                'round',
                'role',
                'status',
                'notified_at',
                'due_at',
                'action_taken_at',
                'type_key'
            ]);
        });
    }
};
