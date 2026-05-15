<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as Http;

/**
 * F-17 — "Discount" admin endpoint.
 *
 * SRS §2.10:
 *   • The Settings → Discount menu should expose this single value.
 *   • Editable by Super Admin only (enforced by route middleware
 *     `role:system-administrator` on the PUT — see api.php).
 *   • The value MUST apply (formerly broken because the lookup key didn't
 *     match the row name; that is now fixed in GenerateWaterSampleInvoice).
 *
 * GET /api/finance/discount  → { value: 50 }
 * PUT /api/finance/discount  → { value: <0..100> }
 */
class DiscountSettingController extends Controller
{
    private const KEY = 'phe-invoice-discount';

    public function show(): JsonResponse
    {
        $setting = $this->loadOrCreate();
        return response()->json([
            'message' => 'Success',
            'data'    => [
                'name'        => self::KEY,
                'description' => $setting->description,
                'value'       => (float) $setting->value,
            ],
        ], Http::HTTP_OK);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'value'       => ['required', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $setting = $this->loadOrCreate();
        $setting->update([
            'value'       => (string) $data['value'],
            'description' => $data['description'] ?? $setting->description,
        ]);

        return response()->json([
            'message' => 'Discount setting updated',
            'data'    => [
                'name'        => self::KEY,
                'description' => $setting->description,
                'value'       => (float) $setting->value,
            ],
        ], Http::HTTP_OK);
    }

    private function loadOrCreate(): Setting
    {
        $row = Setting::query()
            ->whereIn('name', [self::KEY, 'PHE Invoice Discount'])
            ->orderByRaw("FIELD(name, '" . self::KEY . "', 'PHE Invoice Discount')")
            ->first();

        if (!$row) {
            $row = Setting::create([
                'name'        => self::KEY,
                'description' => 'Discount percentage applied to PHE Water Sample Invoices (0–100).',
                'value'       => '50',
            ]);
        }
        return $row;
    }
}
