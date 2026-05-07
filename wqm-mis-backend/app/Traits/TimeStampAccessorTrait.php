<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait TimeStampAccessorTrait
{
    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->setTimezone(config('app.timezone'))->format('d M, Y H:i'),
        );
    }


    protected function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->setTimezone(config('app.timezone'))->format('d M, Y H:i'),
        );
    }


}
