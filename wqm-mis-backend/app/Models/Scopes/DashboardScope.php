<?php

namespace App\Models\Scopes;

use App\Http\Requests\DashboardRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class DashboardScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        if (isset($model->tehsil_id)) {
            $builder->where('tehsil_id', '=', $model->tehsil_id);
        }

        if (isset($model->district_id)) {
            $builder->where('district_id', '=', $model->district_id);
        }

        if (isset($model->laboratory_id)) {
            $builder->where('laboratory_id', '=', $model->laboratory_id);
        }
    }
}
