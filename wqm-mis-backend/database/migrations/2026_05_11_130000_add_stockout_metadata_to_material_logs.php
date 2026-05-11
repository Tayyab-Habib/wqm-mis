<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('material_logs', function (Blueprint $table) {
            $table->string('type', 32)->nullable()->after('status');
            $table->string('recipient_name', 255)->nullable()->after('type');
            $table->string('recipient_role', 255)->nullable()->after('recipient_name');
            $table->string('sample_ref', 255)->nullable()->after('recipient_role');
            $table->text('remarks')->nullable()->after('sample_ref');
            $table->foreignId('recipient_lab_id')->nullable()->after('remarks')
                ->constrained('laboratories')->nullOnDelete();
            $table->foreignId('demand_id')->nullable()->after('recipient_lab_id')
                ->constrained('inventories')->nullOnDelete();
            $table->string('dispatch_reference', 255)->nullable()->after('demand_id');
        });

        Schema::table('laboratory_material_logs', function (Blueprint $table) {
            $table->string('type', 32)->nullable()->after('status');
            $table->string('recipient_name', 255)->nullable()->after('type');
            $table->string('recipient_role', 255)->nullable()->after('recipient_name');
            $table->string('sample_ref', 255)->nullable()->after('recipient_role');
            $table->text('remarks')->nullable()->after('sample_ref');
            $table->foreignId('recipient_lab_id')->nullable()->after('remarks')
                ->constrained('laboratories')->nullOnDelete();
            $table->foreignId('demand_id')->nullable()->after('recipient_lab_id')
                ->constrained('inventories')->nullOnDelete();
            $table->string('dispatch_reference', 255)->nullable()->after('demand_id');
        });
    }

    public function down(): void
    {
        Schema::table('material_logs', function (Blueprint $table) {
            $table->dropForeign(['recipient_lab_id']);
            $table->dropForeign(['demand_id']);
            $table->dropColumn(['type', 'recipient_name', 'recipient_role', 'sample_ref', 'remarks', 'recipient_lab_id', 'demand_id', 'dispatch_reference']);
        });

        Schema::table('laboratory_material_logs', function (Blueprint $table) {
            $table->dropForeign(['recipient_lab_id']);
            $table->dropForeign(['demand_id']);
            $table->dropColumn(['type', 'recipient_name', 'recipient_role', 'sample_ref', 'remarks', 'recipient_lab_id', 'demand_id', 'dispatch_reference']);
        });
    }
};
