<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_dispatches', function (Blueprint $table) {
            // ── Shared fields ─────────────────────────────────────────
            $table->string('reference_no')->nullable()->after('subject');
            $table->string('category')->nullable()->after('reference_no');
            $table->string('priority')->nullable()->after('category');       // Routine / Urgent / Immediate
            $table->text('remarks')->nullable()->after('priority');

            // ── Diary (Inward) specific ───────────────────────────────
            $table->string('from_sender')->nullable()->after('remarks');     // From (sender / organisation)
            $table->string('addressed_to')->nullable()->after('from_sender');
            $table->boolean('action_required')->nullable()->after('addressed_to');
            $table->date('action_due_date')->nullable()->after('action_required');
            $table->text('action_taken')->nullable()->after('action_due_date');
            $table->string('action_status')->nullable()->after('action_taken'); // Pending / In Progress / Completed

            // ── Dispatch (Outward) specific ───────────────────────────
            $table->string('to_recipient')->nullable()->after('action_status');
            $table->string('reference_diary_no')->nullable()->after('to_recipient'); // linked Diary No. if in reply
            $table->string('mode_of_dispatch')->nullable()->after('reference_diary_no'); // Hand Delivery / Post / Courier / Email / Fax
            $table->string('dispatch_reference_no')->nullable()->after('mode_of_dispatch');
            $table->string('prepared_by')->nullable()->after('dispatch_reference_no');
            $table->string('dispatched_by')->nullable()->after('prepared_by');
        });
    }

    public function down(): void
    {
        Schema::table('diary_dispatches', function (Blueprint $table) {
            $table->dropColumn([
                'reference_no', 'category', 'priority', 'remarks',
                'from_sender', 'addressed_to', 'action_required',
                'action_due_date', 'action_taken', 'action_status',
                'to_recipient', 'reference_diary_no', 'mode_of_dispatch',
                'dispatch_reference_no', 'prepared_by', 'dispatched_by',
            ]);
        });
    }
};
