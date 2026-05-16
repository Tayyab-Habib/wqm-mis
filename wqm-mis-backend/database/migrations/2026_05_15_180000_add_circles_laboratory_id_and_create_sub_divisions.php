<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarchy completion per PHE Heirarchy (3).xlsx — adds:
 *
 *   1. circles.laboratory_id — each PHE Circle is served by exactly one HUB Lab.
 *      A lab can serve multiple circles (e.g. Central Lab Peshawar covers
 *      SE Khyber + SE Peshawar). Nullable so existing rows survive backfill.
 *
 *   2. sub_divisions — the leaf level of the PHED hierarchy beneath
 *      phed_divisions. No role consumes it yet (future Sub-Engineer role),
 *      but the data model captures it so seeded data is complete.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('circles') && !Schema::hasColumn('circles', 'laboratory_id')) {
            Schema::table('circles', function (Blueprint $table) {
                $table->foreignId('laboratory_id')->nullable()->after('region_id')
                    ->constrained('laboratories')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('sub_divisions')) {
            Schema::create('sub_divisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('phed_division_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['phed_division_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_divisions');

        if (Schema::hasColumn('circles', 'laboratory_id')) {
            Schema::table('circles', function (Blueprint $table) {
                $table->dropConstrainedForeignId('laboratory_id');
            });
        }
    }
};
