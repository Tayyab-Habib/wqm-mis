<?php

namespace App\Http\Controllers\Reports;

use App\Enums\WaterSampleResultEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ContaminantWiseReportGraphRequest;
use App\Models\Test;
use App\Models\WaterScheme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContaminantWiseReportController extends Controller
{
    public function map(ContaminantWiseReportGraphRequest $request)
    {
        $waterParameters = WaterScheme::query()
            ->select(['id', 'name', 'address', 'latitude', 'longitude', 'district_id'])
            ->with(['district'])
            ->when(isset($request->test_id), fn($query) => $query->whereHas('waterSamples.waterSampleDetails', fn(Builder $query) => $query->where('test_id', $request->test_id)))
            ->withWhereHas('waterSamples', function ($query) use ($request) {
                $query->select('id', 'water_samples.water_scheme_id', 'sampled_at', 'result');
                $query->when(isset($request->water_sample_id),
                    fn(Builder $query) => $query->whereHas('waterSampleDetails',
                        fn(Builder $query) => $query->where('test_id', '=', $request->test_id)
                    )
                );
                $query->when(isset($request->date_from, $request->date_to), function (Builder $query) use ($request) {
                    $query->whereBetween('created_at', [
                        Carbon::parse($request->date_from)->startOfDay(),
                        Carbon::parse($request->date_to)->endOfDay()
                    ]);
                });
                $query->when(isset($request->district_id), fn(Builder $query) => $query->where('district_id', '=', $request->district_id));
                $query->orderBy('sampled_at', 'desc')
                    ->limit(1);
            })
            ->get();

        return response()->json([
            'data' => $waterParameters,
            'message' => 'Success fetching Water Parameters reporting',
        ]);
    }
}
