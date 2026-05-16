<?php

namespace App\Traits;

use App\Enums\DurationEnum;
use App\Models\User;
use App\Models\WaterSamples\WaterSample;
use App\Services\AuthScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait DashboardFilterTrait
{
    public function scopeApplyDashboardFilters(Builder $query, $request, string $tableAlias, string $column = 'created_at')
    {
        return $query
            // RBAC: apply role-driven scope on water_samples queries.
            // No-op for unscoped roles (SA/manager/view-only/general-view);
            // filters by region/circle/phed_division/lab for everyone else.
            ->when($tableAlias === (new WaterSample)->getTable(), function ($q) {
                return AuthScope::waterSamples($q, auth()->user());
            })
            // Sample type lives on collectable_type, not in the slug string.
            // PHE = collectable_type is App\Models\User; Private = anything else.
            ->when($tableAlias === (new WaterSample)->getTable() && isset($request->type), function ($query) use ($request) {
                if ($request->type === 'PHE') {
                    return $query->where('water_samples.collectable_type', User::class);
                }
                if ($request->type === 'Private') {
                    return $query->where('water_samples.collectable_type', '!=', User::class);
                }
                return $query;
            })
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
