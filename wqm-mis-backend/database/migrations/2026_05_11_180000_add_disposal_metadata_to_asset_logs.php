<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('asset_logs', function (Blueprint $table) {
            $table->string('type', 32)->nullable()->after('status');
            $table->string('recipient_name', 255)->nullable()->after('type');
            $table->string('recipient_role', 255)->nullable()->after('recipient_name');
            $table->string('asset_ref', 255)->nullable()->after('recipient_role');
            $table->text('remarks')->nullable()->after('asset_ref');
            $table->foreignId('recipient_lab_id')->nullable()->after('remarks')
                ->constrained('laboratories')->nullOnDelete();
            $table->string('dispatch_reference', 255)->nullable()->after('recipient_lab_id');
        });

        Schema::table('laboratory_asset_logs', function (Blueprint $table) {
            $table->string('type', 32)->nullable()->after('status');
            $table->string('recipient_name', 255)->nullable()->after('type');
            $table->string('recipient_role', 255)->nullable()->after('recipient_name');
            $table->string('asset_ref', 255)->nullable()->after('recipient_role');
            $table->text('remarks')->nullable()->after('asset_ref');
            $table->foreignId('recipient_lab_id')->nullable()->after('remarks')
                ->constrained('laboratories')->nullOnDelete();
            $table->string('dispatch_reference', 255)->nullable()->after('recipient_lab_id');
        });
    }

    public function down(): void
    {
        Schema::table('asset_logs', function (Blueprint $table) {
            $table->dropForeign(['recipient_lab_id']);
            $table->dropColumn(['type', 'recipient_name', 'recipient_role', 'asset_ref', 'remarks', 'recipient_lab_id', 'dispatch_reference']);
        });

        Schema::table('laboratory_asset_logs', function (Blueprint $table) {
            $table->dropForeign(['recipient_lab_id']);
            $table->dropColumn(['type', 'recipient_name', 'recipient_role', 'asset_ref', 'remarks', 'recipient_lab_id', 'dispatch_reference']);
        });
    }
};
