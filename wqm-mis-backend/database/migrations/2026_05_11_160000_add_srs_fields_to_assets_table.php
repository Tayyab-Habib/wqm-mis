<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS §2.7-2 Inventory Register (Non-consumables) requires fields:
 *   Item Name | Category | Item Code (unique) | Quantity | Condition |
 *   Date of Purchase | Purchase Value | Location | Last Verified | Remarks
 *
 * Existing `assets` table only has: name, quantity, unit, date_of_expiry,
 * status, specification, country, agency.
 *
 * This migration adds the missing SRS fields + a `kind` discriminator so the
 * same table can power both Inventory (§2.7-2) and Equipment (§2.7-3) views.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('kind', 16)->default('inventory')->after('name');     // inventory | equipment
            $table->string('category', 64)->nullable()->after('kind');
            $table->string('item_code', 64)->nullable()->unique()->after('category');
            $table->string('condition', 16)->nullable()->after('status');         // good | fair | poor | condemned
            $table->date('date_of_purchase')->nullable()->after('condition');
            $table->decimal('purchase_value', 14, 2)->nullable()->after('date_of_purchase');
            $table->string('location', 255)->nullable()->after('purchase_value');
            $table->date('last_verified')->nullable()->after('location');
            $table->text('remarks')->nullable()->after('last_verified');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'kind', 'category', 'item_code', 'condition',
                'date_of_purchase', 'purchase_value', 'location',
                'last_verified', 'remarks',
            ]);
        });
    }
};
