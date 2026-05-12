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
            $table->unsignedBigInteger('sbp_submission_id')->nullable()->after('water_sample_invoice_id');
            $table->foreign('sbp_submission_id')->references('id')->on('sbp_submissions')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('water_sample_invoice_logs', function (Blueprint $table) {
            $table->dropForeign(['sbp_submission_id']);
            $table->dropColumn('sbp_submission_id');
        });
    }
};
