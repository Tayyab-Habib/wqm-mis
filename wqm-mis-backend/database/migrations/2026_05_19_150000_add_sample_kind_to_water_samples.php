<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adds water_samples.sample_kind to distinguish PHE / Private / PT samples
 * durably.
 *
 * Background: the polymorphic `collectable_type` column stores a model class
 * (User::class for PHE & PT, Client::class for Private). That conflates PHE
 * and PT — both have collectable_type = User::class — so we can't tell them
 * apart from the polymorphic column alone. Slug generators and reports were
 * inferring the kind from the polymorphic class, which silently classified PT
 * samples as PHE.
 *
 * `sample_kind` is the logical "what kind of sample" enum that the FE submits
 * and we persist as-is. Reports and slug generation should consult this
 * column, not the polymorphic class.
 *
 * Backfill: every existing row gets sample_kind = PHE if collectable_type =
 * User::class, else Private. This matches the inference rule the old code was
 * using, so behavior on existing data is unchanged.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('water_samples', function (Blueprint $table) {
            $table->string('sample_kind', 20)
                ->nullable()
                ->after('collectable_id')
                ->index();
        });

        // Backfill existing rows: PHE for User-class collectables, Private otherwise.
        // PT samples will only exist for new submissions post-migration.
        DB::statement("
            UPDATE water_samples
            SET sample_kind = CASE
                WHEN collectable_type = ? THEN 'PHE'
                ELSE 'Private'
            END
            WHERE sample_kind IS NULL
        ", [\App\Models\User::class]);
    }

    public function down(): void
    {
        Schema::table('water_samples', function (Blueprint $table) {
            $table->dropIndex(['sample_kind']);
            $table->dropColumn('sample_kind');
        });
    }
};
