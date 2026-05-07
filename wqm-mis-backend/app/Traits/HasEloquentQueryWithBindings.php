<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasEloquentQueryWithBindings
{
    /**
     * Combines SQL and its bindings
     *
     * @param Builder $query
     * @return string
     */
    public static function get(Builder $query): string
    {
        return vsprintf(str_replace('?', '%s', str_replace('%', '%%', $query->toSql())), collect($query->getBindings())->map(function ($binding) {
            return is_numeric($binding) ? $binding : "'{$binding}'";
        })->toArray());
    }
}
