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
        // Use raw SQL to avoid Doctrine/DBAL compatibility issues in this environment
        DB::statement('ALTER TABLE water_sample_invoices MODIFY water_sample_id BIGINT UNSIGNED NULL');

        Schema::table('water_sample_invoices', function (Blueprint $table) {
            $table->boolean('is_clubbed')->default(false)->after('status');
            $table->unsignedBigInteger('clubbed_invoice_id')->nullable()->after('is_clubbed');
            $table->date('period_from')->nullable()->after('clubbed_invoice_id');
            $table->date('period_to')->nullable()->after('period_from');
            $table->string('clubbed_slug')->nullable()->after('period_to');
            
            $table->foreign('clubbed_invoice_id')
                  ->references('id')
                  ->on('water_sample_invoices')
                  ->onDelete('set null');
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
            $table->dropForeign(['clubbed_invoice_id']);
            $table->dropColumn(['is_clubbed', 'clubbed_invoice_id', 'period_from', 'period_to', 'clubbed_slug']);
        });

        DB::statement('ALTER TABLE water_sample_invoices MODIFY water_sample_id BIGINT UNSIGNED NOT NULL');
    }
};
