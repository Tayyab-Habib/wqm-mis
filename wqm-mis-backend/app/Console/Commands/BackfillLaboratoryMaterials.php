<?php

namespace App\Console\Commands;

use App\Enums\MaterialLogStatusEnum;
use App\Models\Material\LaboratoryMaterial;
use App\Models\Material\Material;
use App\Models\Material\MaterialLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off cleanup: any `materials` row created BEFORE the 4-table workflow
 * was added to MaterialController::store has no matching `laboratory_materials`
 * row, so Stock-Out from any lab fails with "not allocated to your laboratory."
 *
 * This command finds those orphans and allocates them to the lab of the user
 * who created the most recent IN material_log. If no IN log exists, the
 * material is reported and skipped (--default-lab=ID overrides this).
 *
 * Usage:
 *   php artisan materials:backfill-lab-allocations
 *   php artisan materials:backfill-lab-allocations --default-lab=1
 *   php artisan materials:backfill-lab-allocations --dry-run
 */
class BackfillLaboratoryMaterials extends Command
{
    protected $signature = 'materials:backfill-lab-allocations
                            {--default-lab= : Laboratory ID to use when no IN-log creator can be identified}
                            {--dry-run : Show what would happen without writing anything}';

    protected $description = 'Create missing laboratory_materials rows for orphan materials so Stock-Out can be logged against them.';

    public function handle(): int
    {
        $dryRun     = (bool) $this->option('dry-run');
        $defaultLab = $this->option('default-lab');

        if ($dryRun) {
            $this->warn('[DRY RUN] No database changes will be written.');
        }

        $orphans = Material::query()
            ->whereDoesntHave('laboratoryMaterials')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphan materials found. Every catalog entry has at least one lab allocation. ✅');
            return self::SUCCESS;
        }

        $this->info("Found {$orphans->count()} orphan material(s) to backfill.");
        $this->newLine();

        $allocated = 0;
        $skipped   = 0;

        foreach ($orphans as $material) {
            // Find the lab of the user who logged the most recent IN entry.
            $latestInLog = MaterialLog::query()
                ->where('material_id', $material->id)
                ->where('status', MaterialLogStatusEnum::IN->value)
                ->orderByDesc('id')
                ->first();

            $labId = null;

            if ($latestInLog && $latestInLog->user_id) {
                $user = User::find($latestInLog->user_id);
                $labId = $user?->laboratoryDetails?->laboratory_id;
            }

            if (!$labId && $defaultLab) {
                $labId = (int) $defaultLab;
            }

            if (!$labId) {
                $this->warn(sprintf(
                    '  ✗ #%d %-40s — no IN-log creator and no --default-lab set, skipping',
                    $material->id,
                    str()->limit($material->name, 38)
                ));
                $skipped++;
                continue;
            }

            $this->line(sprintf(
                '  → #%d %-40s → laboratory_id=%d (qty=%s)',
                $material->id,
                str()->limit($material->name, 38),
                $labId,
                $material->available_quantity
            ));

            if ($dryRun) {
                $allocated++;
                continue;
            }

            try {
                DB::beginTransaction();

                $labMaterial = LaboratoryMaterial::query()->create([
                    'laboratory_id'      => $labId,
                    'material_id'        => $material->id,
                    'quantity'           => (string) $material->available_quantity,
                    'available_quantity' => $material->available_quantity,
                    'unit'               => $material->unit,
                    'threshold'          => (string) $material->threshold,
                    'status'             => $material->status?->value ?? 'active',
                ]);

                // Synthetic lab-level IN log, linked to the global IN log if we
                // found one, so the audit trail stays consistent.
                $labMaterial->laboratoryMaterialLogs()->create([
                    'material_log_id' => $latestInLog?->id,
                    'date_of_expiry'  => $latestInLog?->date_of_expiry,
                    'quantity'        => (string) $material->available_quantity,
                    'unit'            => $material->unit,
                    'status'          => MaterialLogStatusEnum::IN->value,
                    'type'            => null,
                    'remarks'         => 'Backfilled by materials:backfill-lab-allocations',
                ]);

                DB::commit();
                $allocated++;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error(sprintf('    ✗ Failed: %s', $e->getMessage()));
                $skipped++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d allocated, %d skipped.',
            $dryRun ? '[DRY RUN]' : 'Done.',
            $allocated,
            $skipped
        ));

        return self::SUCCESS;
    }
}
