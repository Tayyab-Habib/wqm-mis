<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill water_samples.region_id where it is NULL, by looking up
 * the sample's district → circle → region.
 *
 * Why: historical samples (and any saved before region_id was wired
 * into the registration form) have NULL region_id. The CE-wise report
 * groups by region_id, so those rows collapse into an "Unknown CE"
 * bucket. Backfilling resolves them to their real CE.
 *
 * Path (verified against schema): district.circle_id → circle.region_id
 * NOTE: divisions.region_id is NULL in this DB, so the join MUST go
 * through circles, not divisions.
 */
class BackfillSampleRegion extends Command
{
    protected $signature   = 'samples:backfill-region {--dry-run : Show counts without writing}';
    protected $description = 'Fill water_samples.region_id from district→circle→region for rows where region_id is NULL';

    public function handle(): int
    {
        $this->info('— Backfill water_samples.region_id —');

        $nullBefore = DB::table('water_samples')
            ->whereNull('deleted_at')
            ->whereNull('region_id')
            ->count();

        $this->line("Samples with NULL region_id before: <fg=yellow>{$nullBefore}</>");

        if ($nullBefore === 0) {
            $this->info('Nothing to do.');
            return self::SUCCESS;
        }

        // How many of those have an inferable region via district→circle→region?
        $inferable = DB::table('water_samples as ws')
            ->join('districts as d', 'ws.district_id', '=', 'd.id')
            ->join('circles as c',   'd.circle_id',   '=', 'c.id')
            ->whereNull('ws.deleted_at')
            ->whereNull('ws.region_id')
            ->whereNotNull('c.region_id')
            ->count();

        $orphans = $nullBefore - $inferable;
        $this->line("  Inferable via district→circle→region: <fg=green>{$inferable}</>");
        $this->line("  Orphans (no district / district has no circle): <fg=red>{$orphans}</>");

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no rows written.');
            return self::SUCCESS;
        }

        if (! $this->confirm("Write {$inferable} updates now?", true)) {
            $this->warn('Aborted.');
            return self::SUCCESS;
        }

        // Single SQL bulk update via INNER JOIN (MySQL-flavoured).
        // Also fills circle_id on samples where it's NULL (same source).
        $affected = DB::affectingStatement(
            'UPDATE water_samples ws
                INNER JOIN districts d ON ws.district_id = d.id
                INNER JOIN circles c   ON d.circle_id   = c.id
             SET ws.region_id = c.region_id,
                 ws.circle_id = COALESCE(ws.circle_id, c.id)
             WHERE ws.deleted_at IS NULL
               AND ws.region_id IS NULL
               AND c.region_id IS NOT NULL'
        );

        $this->info("Updated rows: {$affected}");

        $nullAfter = DB::table('water_samples')
            ->whereNull('deleted_at')
            ->whereNull('region_id')
            ->count();

        $this->line("Samples with NULL region_id after:  <fg=yellow>{$nullAfter}</>");

        if ($nullAfter > 0) {
            $this->warn("Remaining {$nullAfter} samples couldn't be resolved (no district, or district has no circle). Inspect those rows manually.");
        }

        return self::SUCCESS;
    }
}
