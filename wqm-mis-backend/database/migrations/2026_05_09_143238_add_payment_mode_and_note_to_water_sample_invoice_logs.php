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
        Schema::table('water_sample_invoice_logs', function (Blueprint $table) {
            $table->string('payment_mode')->nullable()->after('balance');
            $table->text('note')->nullable()->after('payment_mode');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('water_sample_invoice_logs', function (Blueprint $table) {
            $table->dropColumn(['payment_mode', 'note']);
        });
    }
};
