<?php

namespace App\Services;

use App\Models\Laboratories\Laboratory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generates SRS-compliant, per-lab, sequential slugs for:
 *   • Clubbed Invoices  →  C/YY/LAB-CODE/CXXXX     (SRS §2.19.2)
 *   • SBP Submissions   →  SBP/YY/LAB-CODE/XXXX    (SRS §2.18 ledger)
 *
 * The counter is stored on `laboratories.next_clubbed_seq` / `next_sbp_seq`
 * and incremented inside a row-level lock so concurrent requests on the
 * same lab can never produce the same slug.
 */
class FinanceSlugService
{
    public function nextClubbedSlug(int $laboratoryId): string
    {
        return DB::transaction(function () use ($laboratoryId) {
            $lab = Laboratory::query()
                ->lockForUpdate()
                ->select(['id', 'code', 'next_clubbed_seq'])
                ->find($laboratoryId);

            if (!$lab) {
                throw new RuntimeException("Laboratory id={$laboratoryId} not found for clubbed slug generation.");
            }

            $seq  = (int) $lab->next_clubbed_seq + 1;
            $code = $this->labCode($lab);

            $lab->forceFill(['next_clubbed_seq' => $seq])->save();

            return sprintf(
                'C/%s/%s/C%s',
                Carbon::now()->format('y'),
                $code,
                str_pad((string) $seq, 4, '0', STR_PAD_LEFT)
            );
        });
    }

    public function nextSbpSlug(int $laboratoryId): string
    {
        return DB::transaction(function () use ($laboratoryId) {
            $lab = Laboratory::query()
                ->lockForUpdate()
                ->select(['id', 'code', 'next_sbp_seq'])
                ->find($laboratoryId);

            if (!$lab) {
                throw new RuntimeException("Laboratory id={$laboratoryId} not found for SBP slug generation.");
            }

            $seq  = (int) $lab->next_sbp_seq + 1;
            $code = $this->labCode($lab);

            $lab->forceFill(['next_sbp_seq' => $seq])->save();

            return sprintf(
                'SBP/%s/%s/%s',
                Carbon::now()->format('y'),
                $code,
                str_pad((string) $seq, 4, '0', STR_PAD_LEFT)
            );
        });
    }

    private function labCode($lab): string
    {
        if (!empty($lab->code)) {
            return strtoupper(substr($lab->code, 0, 8));
        }
        // Defensive: shouldn't happen post-migration but never emit the
        // legacy hard-coded "LAB" string into a slug.
        return 'LAB' . $lab->id;
    }
}
