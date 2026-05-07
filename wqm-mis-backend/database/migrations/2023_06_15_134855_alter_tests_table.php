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
        Schema::table('tests', function (Blueprint $table) {
            $table->decimal('who_guideline_start')->default(0)->after('rate');
            $table->decimal('who_guideline_end')->default(0)->after('who_guideline_start');
            $table->decimal('laboratory_guideline_start')->default(0)->after('who_guideline_end');
            $table->decimal('laboratory_guideline_end')->default(0)->after('laboratory_guideline_start');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn([
                'who_guideline_start',
                'who_guideline_end',
                'laboratory_guideline_start',
                'laboratory_guideline_end',
            ]);
        });
    }
};
