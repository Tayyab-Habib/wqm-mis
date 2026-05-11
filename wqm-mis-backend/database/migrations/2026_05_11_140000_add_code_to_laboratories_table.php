<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            // 3-letter (or short) code used in Demand IDs (e.g. KHT, CLB, MRD).
            $table->string('code', 8)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('laboratories', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
