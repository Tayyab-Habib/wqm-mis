<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('phed_divisions', function (Blueprint $table) {
            $table->foreignId('district_id')
                ->after('id')
                ->nullable()
                ->index()
                ->constrained('districts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('phed_divisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('district_id');
        });
    }
};
