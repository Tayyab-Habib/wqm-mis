<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\WaterSamples\WaterSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Public WQ results portal (SRS §1.2 — "Public: General citizens; Published
 * WQ results, read only").
 *
 * Unauthenticated endpoint. Returns ONLY redacted, non-identifying data
 * (sample slug, WSS name, district, date, result). Excludes any private
 * client info, internal status fields, or hierarchy IDs.
 *
 * Search supports:
 *   - slug (partial match) — primary lookup
 *   - scheme_name (partial match on water_scheme name)
 *   - district_name (partial match)
 *
 * Capped at 50 results to prevent enumeration / scraping.
 */
class PublicResultsController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'slug'          => ['nullable', 'string', 'max:50'],
            'scheme_name'   => ['nullable', 'string', 'max:255'],
            'district_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Must provide at least one search term — prevents "list everything" enumeration.
        if (!$request->filled('slug')
            && !$request->filled('scheme_name')
            && !$request->filled('district_name')) {
            return response()->json([
                'message' => 'Please provide a sample ID, scheme name, or district to search.',
                'data'    => [],
            ], SymfonyResponse::HTTP_OK);
        }

        $query = WaterSample::query()
            ->whereNotNull('result')
            ->where('is_draft', false)
            ->with([
                'waterScheme:id,name',
                'district:id,name',
            ])
            ->when($request->filled('slug'), function ($q) use ($request) {
                $q->where('slug', 'like', '%'.$request->slug.'%');
            })
            ->when($request->filled('scheme_name'), function ($q) use ($request) {
                $q->whereHas('waterScheme', fn($wq) => $wq->where('name', 'like', '%'.$request->scheme_name.'%'));
            })
            ->when($request->filled('district_name'), function ($q) use ($request) {
                $q->whereHas('district', fn($dq) => $dq->where('name', 'like', '%'.$request->district_name.'%'));
            })
            ->limit(50);

        $samples = $query->get(['id', 'slug', 'water_scheme_id', 'district_id', 'sampled_at', 'result']);

        $redacted = $samples->map(function ($s) {
            $result = is_numeric($s->result)
                ? ((string) $s->result === '1' ? 'Fit' : ($s->result === '2' ? 'Unfit' : 'Pending'))
                : $s->result;
            return [
                'sample_id'   => $s->slug,
                'scheme_name' => $s->waterScheme?->name ?? '—',
                'district'    => $s->district?->name ?? '—',
                'sampled_at'  => $s->sampled_at,
                'result'      => $result,
            ];
        });

        return response()->json([
            'message' => 'Success',
            'data'    => $redacted,
            'count'   => $redacted->count(),
        ], SymfonyResponse::HTTP_OK);
    }
}
