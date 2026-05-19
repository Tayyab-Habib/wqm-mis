<?php

namespace App\Http\Controllers\Search;

use App\Enums\WaterSampleCurrentStatusEnum;
use App\Enums\WaterSampleTestResultEnum;
use App\Http\Controllers\Controller;
use App\Models\WaterScheme;
use App\Models\WaterSamples\WaterSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * WSS Map endpoint — returns water schemes with valid coordinates plus each
 * scheme's latest water-sample result (Fit / Unfit / Untested) and last
 * sampling date, with the filters the WSS Map page exposes.
 */
class WaterSchemeMapController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $fromDate         = $request->input('from_date');
        $toDate           = $request->input('to_date');
        $divisionId       = $request->input('division_id');
        $phedDivisionId   = $request->input('phed_division_id');
        $districtId       = $request->input('district_id');
        $resultFilter     = $request->input('result');   // 'Fit' | 'Unfit' | 'Untested'

        // ── Per-scheme latest-sample summary ─────────────────────────────
        $latestSampleQuery = WaterSample::query()
            ->selectRaw('water_scheme_id, MAX(sampled_at) as last_sampled_at')
            ->whereNotNull('water_scheme_id')
            ->whereNull('deleted_at')
            ->groupBy('water_scheme_id');

        if ($fromDate) $latestSampleQuery->whereDate('sampled_at', '>=', $fromDate);
        if ($toDate)   $latestSampleQuery->whereDate('sampled_at', '<=', $toDate);

        $latestPerScheme = $latestSampleQuery->get()->keyBy('water_scheme_id');

        // Pull the actual sample row matching (water_scheme_id, sampled_at) for the result
        $sampleRows = WaterSample::query()
            ->select('id', 'water_scheme_id', 'sampled_at', 'current_status', 'result')
            ->whereIn('water_scheme_id', $latestPerScheme->keys())
            ->get()
            ->groupBy('water_scheme_id')
            ->map(function ($rows, $schemeId) use ($latestPerScheme) {
                $target = $latestPerScheme[$schemeId]->last_sampled_at ?? null;
                if (!$target) return null;
                // sampled_at has an accessor that pre-formats — use raw original for compare
                return $rows->first(function ($r) use ($target) {
                    return $r->getRawOriginal('sampled_at') === $target;
                }) ?? $rows->sortByDesc(fn ($r) => $r->getRawOriginal('sampled_at'))->first();
            })
            ->filter();

        // ── Schemes ─────────────────────────────────────────────────────
        $query = WaterScheme::query()
            ->with(['district:id,name', 'phedDivision:id,name'])
            ->whereNot('name', '=', '-')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNot('latitude', '=', '-')
            ->whereNot('longitude', '=', '-')
            ->whereNot('latitude', '=', '')
            ->whereNot('longitude', '=', '')
            ->select('id', 'name', 'address', 'latitude', 'longitude', 'district_id', 'phed_division_id', 'division_id');

        if ($districtId)     $query->where('district_id', $districtId);
        if ($divisionId)     $query->where('division_id', $divisionId);
        if ($phedDivisionId) $query->where('phed_division_id', $phedDivisionId);

        $schemes = $query->get()->map(function ($w) use ($sampleRows, $fromDate, $toDate) {
            $lat = is_numeric($w->latitude) ? (float) $w->latitude : null;
            $lng = is_numeric($w->longitude) ? (float) $w->longitude : null;

            $last = $sampleRows[$w->id] ?? null;
            $result = $this->resolveResult($last);
            $lastSampledAt = $last?->getRawOriginal('sampled_at');

            // If a date filter is set, schemes whose latest sample falls outside
            // the window should appear as Untested rather than carry a stale label.
            if (($fromDate || $toDate) && !$last) {
                $result = 'Untested';
                $lastSampledAt = null;
            }

            return [
                'id'                => $w->id,
                'name'              => $w->name,
                'address'           => $w->address,
                'latitude'          => $lat,
                'longitude'         => $lng,
                'district'          => $w->district ? ['id' => $w->district->id, 'name' => $w->district->name] : null,
                'phed_division'     => $w->phedDivision ? ['id' => $w->phedDivision->id, 'name' => $w->phedDivision->name] : null,
                'last_sample_result'=> $result,
                'last_sampled_at'   => $lastSampledAt,
            ];
        })
        // Drop any with unparseable coords or out-of-range values
        ->filter(function ($w) {
            return $w['latitude'] !== null
                && $w['longitude'] !== null
                && $w['latitude'] >= -90  && $w['latitude'] <= 90
                && $w['longitude'] >= -180 && $w['longitude'] <= 180;
        })
        ->values();

        if ($resultFilter) {
            $schemes = $schemes->where('last_sample_result', $resultFilter)->values();
        }

        $counts = [
            'total'    => $schemes->count(),
            'fit'      => $schemes->where('last_sample_result', 'Fit')->count(),
            'unfit'    => $schemes->where('last_sample_result', 'Unfit')->count(),
            'untested' => $schemes->where('last_sample_result', 'Untested')->count(),
        ];

        return response()->json([
            'message' => 'Success retrieving WSS map data',
            'data'    => $schemes,
            'counts'  => $counts,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Map a sample's raw column values to one of: Fit / Unfit / Untested.
     * Handles both legacy string ('Fit'/'Unfit') and current int (1/2) values
     * for water_samples.result, plus the current_status enum as a fallback.
     */
    private function resolveResult($sample): string
    {
        if (!$sample) return 'Untested';

        $raw = $sample->getRawOriginal('result');
        if ($raw !== null && $raw !== '') {
            $s = strtolower((string) $raw);
            if ($s === '1' || $s === 'fit')   return 'Fit';
            if ($s === '2' || $s === 'unfit') return 'Unfit';
        }

        $cs = $sample->getRawOriginal('current_status');
        if ($cs !== null) {
            $cs = (int) $cs;
            if ($cs === WaterSampleCurrentStatusEnum::FIT->value)   return 'Fit';
            if ($cs === WaterSampleCurrentStatusEnum::UNFIT->value) return 'Unfit';
            if ($cs === WaterSampleCurrentStatusEnum::CLOSED->value) return 'Fit';
        }

        return 'Untested';
    }
}
