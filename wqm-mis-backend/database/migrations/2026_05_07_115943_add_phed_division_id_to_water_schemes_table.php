<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_schemes', function (Blueprint $table) {
            $table->foreignId('phed_division_id')
                ->nullable()
                ->after('division_id')
                ->constrained('phed_divisions')
                ->restrictOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('water_schemes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('phed_division_id');
        });
    }
};
