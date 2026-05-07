<?php

namespace App\Traits;

use App\Enums\DurationEnum;
use App\Models\WaterSamples\WaterSample;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait DashboardFilterTrait
{
    public function scopeApplyDashboardFilters(Builder $query, $request, string $tableAlias, string $column = 'created_at')
    {
        return $query
            ->when($tableAlias === (new WaterSample)->getTable() && isset($request->type), fn($query) => $query->where('slug', 'LIKE', '%'.$request->type.'%'))
            ->when(isset($request->district_id), fn($query) => $query->where($tableAlias . '.district_id', $request->district_id))
            ->when(isset($request->division_id), fn($query) => $query->where($tableAlias . '.division_id', $request->division_id))
            ->when(isset($request->duration) && $tableAlias !== 'laboratories', function ($query) use ($request, $column) {
                switch ($request->duration) {
                    case DurationEnum::ANNUAL->value:
                        return $query->when(isset($request->annual), fn($query) => $query->whereYear($column, $request->annual));
//                        return $query->where(function ($query) use ($request, $column) {
//                            foreach ($request->annual as $year) {
//                                $query->orWhereYear($column, $year);
//                            }
//                        });
                    case DurationEnum::MONTH->value:
                        return $query->whereBetween($column, [
                            Carbon::parse($request->start_month)->startOfDay(),
                            Carbon::parse($request->end_month)->endOfDay()
                        ]);
                    case DurationEnum::QUARTER->value:
                        switch ($request->quarter) {
                            case 'Q1':
                                $startDate = $request->annual . '-' . now()->startOfYear()->format('m-d');
                                $endDate = $request->annual . '-' . now()->startOfYear()->addMonth(2)->endOfMonth()->format('m-d');
                                break;
                            case 'Q2':
                                $startDate = $request->annual . '-' . now()->startOfYear()->addMonth(3)->startOfMonth()->format('m-d');
                                $endDate = $request->annual . '-' . now()->startOfYear()->addMonth(5)->endOfMonth()->format('m-d');
                                break;
                            case 'Q3':
                                $startDate = $request->annual . '-' . now()->startOfYear()->addMonth(6)->startOfMonth()->format('m-d');
                                $endDate = $request->annual . '-' . now()->startOfYear()->addMonth(8)->endOfMonth()->format('m-d');
                                break;
                            case 'Q4':
                                $startDate = $request->annual . '-' . now()->startOfYear()->addMonth(9)->startOfMonth()->format('m-d');
                                $endDate = $request->annual . '-' . now()->startOfYear()->addMonth(11)->endOfMonth()->format('m-d');
                                break;
                        }
                        return $query->whereBetween($column, [$startDate, $endDate]);

                }
            });
    }
}
