<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait IsActiveScopeTrait
{
    public function scopeIsActive(Builder $query): void
    {
        $query->where('is_active', '=', true);
    }

}
