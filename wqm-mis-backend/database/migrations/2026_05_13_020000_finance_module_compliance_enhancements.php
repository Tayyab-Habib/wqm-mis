<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema enhancements for SRS-compliant Finance Module:
 *
 *  • F-01 / F-03 — `water_sample_invoice_logs`: payment_date, receipt_no, received_by_name
 *  • F-09 / D-04 — `laboratories`: next_clubbed_seq, next_sbp_seq + backfilled `code`
 *  • F-16        — `water_sample_invoices`: online_viewing_password
 *  • D-08        — Widen money columns on invoices and logs to decimal(15,2)
 *  • F-17        — Ensure correctly-named discount setting row exists
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── water_sample_invoice_logs: payment audit fields (F-03) ──────────
        Schema::table('water_sample_invoice_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('water_sample_invoice_logs', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('balance');
            }
            if (!Schema::hasColumn('water_sample_invoice_logs', 'receipt_no')) {
                $table->string('receipt_no')->nullable()->after('payment_date');
            }
            if (!Schema::hasColumn('water_sample_invoice_logs', 'received_by_name')) {
                $table->string('received_by_name')->nullable()->after('user_id');
            }
        });

        // ── water_sample_invoices: online viewing password (F-16) ───────────
        Schema::table('water_sample_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('water_sample_invoices', 'online_viewing_password')) {
                $table->string('online_viewing_password')->nullable()->after('clubbed_slug');
            }
        });

        // ── laboratories: per-lab clubbed + SBP counters (F-09, D-04) ───────
        Schema::table('laboratories', function (Blueprint $table) {
            if (!Schema::hasColumn('laboratories', 'next_clubbed_seq')) {
                $table->unsignedInteger('next_clubbed_seq')->default(0)->after('code');
            }
            if (!Schema::hasColumn('laboratories', 'next_sbp_seq')) {
                $table->unsignedInteger('next_sbp_seq')->default(0)->after('next_clubbed_seq');
            }
        });

        // ── D-08: widen money columns to decimal(15,2) ──────────────────────
        // Raw SQL because Doctrine/DBAL isn't reliably installed for column
        // type changes in this stack.
        DB::statement('ALTER TABLE water_sample_invoices MODIFY price            DECIMAL(15,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE water_sample_invoices MODIFY paid             DECIMAL(15,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE water_sample_invoices MODIFY balance          DECIMAL(15,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE water_sample_invoices MODIFY net_amount       DECIMAL(15,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE water_sample_invoice_logs MODIFY paid    DECIMAL(15,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE water_sample_invoice_logs MODIFY balance DECIMAL(15,2) NOT NULL DEFAULT 0');

        // ── Backfill lab codes for the existing 9 labs (F-09) ───────────────
        $defaults = [
            'Central Laboratory Peshawar'             => 'PWR',
            'Swat Laboratory'                         => 'SWT',
            'Timergara (at Batkhela) Laboratory'      => 'TMG',
            'Kohat Laboratory'                        => 'KHT',
            'Mardan Laboratory'                       => 'MRD',
            'Di Khan Laboratory'                      => 'DIK',
            'Bannu/lakki Laboratory'                  => 'BNU',
            'Abbottabad Laboratory'                   => 'ABT',
        ];
        foreach ($defaults as $name => $code) {
            DB::table('laboratories')->where('name', $name)->whereNull('code')->update(['code' => $code]);
        }
        // Generic fallback: first 3 alpha chars of the lab name, uppercase
        $remaining = DB::table('laboratories')->whereNull('code')->get(['id', 'name']);
        foreach ($remaining as $lab) {
            $alpha = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $lab->name), 0, 3));
            if ($alpha === '') {
                $alpha = 'LAB';
            }
            DB::table('laboratories')->where('id', $lab->id)->update(['code' => $alpha]);
        }

        // ── Backfill next_clubbed_seq based on the highest existing slug ────
        // We can't perfectly back-derive from random historical slugs, but we
        // set it to the count of currently-clubbed-invoices per lab so future
        // generations don't collide with anything that already exists.
        $perLab = DB::table('water_sample_invoices as p')
            ->join('water_sample_invoices as c', 'c.clubbed_invoice_id', '=', 'p.id')
            ->join('water_samples as ws', 'c.water_sample_id', '=', 'ws.id')
            ->where('p.is_clubbed', 1)
            ->selectRaw('ws.laboratory_id as lab_id, COUNT(DISTINCT p.id) as n')
            ->groupBy('ws.laboratory_id')
            ->get();
        foreach ($perLab as $row) {
            if ($row->lab_id) {
                DB::table('laboratories')->where('id', $row->lab_id)->update(['next_clubbed_seq' => $row->n]);
            }
        }

        // ── Backfill next_sbp_seq per lab ───────────────────────────────────
        $sbpPerLab = DB::table('sbp_submissions')
            ->selectRaw('laboratory_id as lab_id, COUNT(*) as n')
            ->whereNotNull('laboratory_id')
            ->groupBy('laboratory_id')
            ->get();
        foreach ($sbpPerLab as $row) {
            DB::table('laboratories')->where('id', $row->lab_id)->update(['next_sbp_seq' => $row->n]);
        }

        // ── F-17: ensure discount setting row uses the canonical key ────────
        // GenerateWaterSampleInvoice looks up by `phe-invoice-discount`. The
        // legacy DB had `PHE Invoice Discount` so the lookup silently fell
        // back to the hard-coded 50. We insert (or align) the canonical row,
        // preserving any existing configured value.
        $existingValue = DB::table('settings')
            ->whereIn('name', ['phe-invoice-discount', 'PHE Invoice Discount'])
            ->value('value');

        $canonicalValue = $existingValue !== null ? $existingValue : '50';

        $canonical = DB::table('settings')->where('name', 'phe-invoice-discount')->first();
        if (!$canonical) {
            DB::table('settings')->insert([
                'name'        => 'phe-invoice-discount',
                'description' => 'Discount percentage applied to PHE Water Sample Invoices (0-100). '
                                 . 'Value of 50 means PHE receives a 50% discount and pays half the rate.',
                'value'       => $canonicalValue,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('water_sample_invoice_logs', function (Blueprint $table) {
            if (Schema::hasColumn('water_sample_invoice_logs', 'payment_date'))     $table->dropColumn('payment_date');
            if (Schema::hasColumn('water_sample_invoice_logs', 'receipt_no'))       $table->dropColumn('receipt_no');
            if (Schema::hasColumn('water_sample_invoice_logs', 'received_by_name')) $table->dropColumn('received_by_name');
        });
        Schema::table('water_sample_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('water_sample_invoices', 'online_viewing_password')) {
                $table->dropColumn('online_viewing_password');
            }
        });
        Schema::table('laboratories', function (Blueprint $table) {
            if (Schema::hasColumn('laboratories', 'next_clubbed_seq')) $table->dropColumn('next_clubbed_seq');
            if (Schema::hasColumn('laboratories', 'next_sbp_seq'))     $table->dropColumn('next_sbp_seq');
        });
        // Money column widening is not reversed: narrowing back to decimal(8,2)
        // could truncate any existing valid data over 999,999.99.
    }
};
