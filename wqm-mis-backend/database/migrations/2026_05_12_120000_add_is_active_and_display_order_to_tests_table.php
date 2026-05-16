<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS §2.2 R-07 (Parameter-wise Report) requires the parameters dropdown
 * to be populated from `WHERE is_active=1 ORDER BY display_order`.
 * Neither column existed on the tests table before.
 *
 * Defaults: all existing tests become active and get display_order=0 so
 * nothing breaks; admins can curate display_order later.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            if (! Schema::hasColumn('tests', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('rate');
            }
            if (! Schema::hasColumn('tests', 'display_order')) {
                $table->integer('display_order')->default(0)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            if (Schema::hasColumn('tests', 'display_order')) {
                $table->dropColumn('display_order');
            }
            if (Schema::hasColumn('tests', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
