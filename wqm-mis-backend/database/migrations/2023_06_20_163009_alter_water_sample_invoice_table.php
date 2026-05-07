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
        Schema::table('water_sample_invoices', function (Blueprint $table) {
            $table->decimal('discount_percentage')->after('invoiceable_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('water_sample_invoices', function (Blueprint $table) {
            $table->dropColumn('discount_percentage');
        });
    }
};
