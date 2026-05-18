<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterSamples\WaterSampleInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ClientPortalController extends Controller
{
    /**
     * Resolve the authenticated client from the request.
     */
    private function client(Request $request): Client
    {
        return $request->attributes->get('portal_client');
    }

    /**
     * GET /api/client-portal/me
     * Return the authenticated client's profile.
     */
    public function me(Request $request): JsonResponse
    {
        $client = $this->client($request);

        return response()->json([
            'message' => 'Success',
            'data'    => [
                'id'                => $client->id,
                'name'              => $client->name,
                'email'             => $client->email,
                'phone'             => $client->phone,
                'address'           => $client->address,
                'organization_name' => $client->organization_name,
                'type'              => $client->type,
            ],
        ]);
    }

    /**
     * GET /api/client-portal/samples
     * All water samples belonging to this client, with analysis results.
     */
    public function samples(Request $request): JsonResponse
    {
        $client  = $this->client($request);

        $samples = WaterSample::withoutGlobalScopes()
            ->where('collectable_type', 'App\\Models\\Client')
            ->where('collectable_id', $client->id)
            ->where('is_draft', 0)
            ->with([
                'laboratory:id,name',
                'district:id,name',
                'waterSampleDetails.test:id,water_quality_parameter,unit,permissible_limits,type',
            ])
            ->orderByDesc('sampled_at')
            ->get()
            ->map(function ($sample) {
                return [
                    'id'           => $sample->id,
                    'slug'         => $sample->slug,
                    'sample_name'  => $sample->sample_name,
                    'source_type'  => $sample->source_type,
                    'sampled_at'   => $sample->sampled_at,
                    'analyzed_at'  => $sample->analyzed_at,
                    'reported_at'  => $sample->reported_at,
                    'result'       => $sample->result,
                    'laboratory'   => $sample->laboratory?->name,
                    'district'     => $sample->district?->name,
                    'parameters'   => $sample->waterSampleDetails->map(fn($d) => [
                        'parameter'    => $d->test?->water_quality_parameter,
                        'type'         => $d->test?->type,
                        'unit'         => $d->test?->unit,
                        'limit'        => $d->test?->permissible_limits,
                        'result'       => $d->analysis_result,
                        'input_result' => $d->input_result,
                    ])->values(),
                ];
            });

        return response()->json([
            'message' => 'Success',
            'data'    => $samples,
        ]);
    }

    /**
     * GET /api/client-portal/invoices
     * All invoices for this client.
     */
    public function invoices(Request $request): JsonResponse
    {
        $client = $this->client($request);

        $invoices = WaterSampleInvoice::withoutGlobalScopes()
            ->where('invoiceable_type', 'App\\Models\\Client')
            ->where('invoiceable_id', $client->id)
            ->with([
                'waterSample:id,slug,sample_name,sampled_at,result',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($inv) => [
                'id'                  => $inv->id,
                'slug'                => $inv->waterSample?->slug,
                'sample_name'         => $inv->waterSample?->sample_name,
                'sampled_at'          => $inv->waterSample?->sampled_at,
                'result'              => $inv->waterSample?->result,
                'price'               => $inv->price,
                'discount_percentage' => $inv->discount_percentage,
                'net_amount'          => $inv->net_amount,
                'paid'                => $inv->paid,
                'balance'             => $inv->balance,
                'status'              => $inv->status,
                'created_at'          => $inv->created_at,
            ]);

        return response()->json([
            'message' => 'Success',
            'data'    => $invoices,
        ]);
    }

    /**
     * PUT /api/client-portal/change-password
     * Client changes their own password.
     *
     * Hardened 2026-05-18:
     *   - Bumped min length 6 → 8 + complexity rules (mixed case + digit)
     *     so trivial passwords like "123456" stop passing.
     *   - Invalidates the current portal_token after successful change so
     *     anyone sitting on a hijacked session loses it. Client must
     *     re-login. Frontend should redirect to /login after a 200 here.
     *   - Rate-limited at the route level (throttle:3,5).
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => [
                'required', 'string', 'min:8', 'confirmed',
                // At least one lowercase, one uppercase, one digit.
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            ],
        ], [
            'new_password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one digit.',
        ]);

        $client = $this->client($request);

        if (!Hash::check($request->current_password, $client->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'errors'  => ['current_password' => ['Current password is incorrect.']],
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $client->update([
            'password'                 => Hash::make($request->new_password),
            // Invalidate token — forces re-login on this and any other device.
            'portal_token'             => null,
            'portal_token_expires_at'  => null,
        ]);

        return response()->json([
            'message' => 'Password updated successfully. Please log in again.',
        ]);
    }

    /**
     * GET /api/client-portal/email-reports
     * Samples that have a final result (Fit/Unfit) — these are the "email reports".
     */
    public function emailReports(Request $request): JsonResponse
    {
        $client = $this->client($request);

        $samples = WaterSample::withoutGlobalScopes()
            ->where('collectable_type', 'App\\Models\\Client')
            ->where('collectable_id', $client->id)
            ->where('is_draft', 0)
            ->whereNotNull('result')
            ->whereIn('result', ['Fit', 'Unfit', '1', '2'])
            ->with([
                'laboratory:id,name,address,phone,email',
                'district:id,name',
                'waterSampleDetails.test:id,water_quality_parameter,unit,permissible_limits,type,criteria',
            ])
            ->orderByDesc('reported_at')
            ->get()
            ->map(function ($sample) {
                $result = in_array($sample->result, ['Fit', '1']) ? 'Fit' : 'Unfit';
                return [
                    'id'          => $sample->id,
                    'slug'        => $sample->slug,
                    'sample_name' => $sample->sample_name,
                    'source_type' => $sample->source_type,
                    'sampled_at'  => $sample->sampled_at,
                    'analyzed_at' => $sample->analyzed_at,
                    'reported_at' => $sample->reported_at,
                    'result'      => $result,
                    'laboratory'  => $sample->laboratory ? [
                        'name'    => $sample->laboratory->name,
                        'address' => $sample->laboratory->address,
                        'phone'   => $sample->laboratory->phone,
                        'email'   => $sample->laboratory->email,
                    ] : null,
                    'district'    => $sample->district?->name,
                    'parameters'  => $sample->waterSampleDetails->map(fn($d) => [
                        'parameter'    => $d->test?->water_quality_parameter,
                        'type'         => $d->test?->type,
                        'unit'         => $d->test?->unit,
                        'limit'        => $d->test?->permissible_limits,
                        'criteria'     => (bool)$d->test?->criteria,
                        'result'       => $d->analysis_result,
                    ])->values(),
                ];
            });

        return response()->json([
            'message' => 'Success',
            'data'    => $samples,
        ]);
    }
}
