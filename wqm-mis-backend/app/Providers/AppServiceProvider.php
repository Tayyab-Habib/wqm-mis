<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        /*
         * Validate morph field
         *
         * @usage
         * 'messagable_id' => ['required', 'morph_exists:messageable_type'],
         * 'messagable_type' => ['required', 'string'],
         */
        Validator::extend('morph_exists', static function ($attribute, $value, $parameters, $validator) {
            if (!$type = Arr::get($validator->getData(), $parameters[0], false)) {
                return false;
            }

            $type = Relation::getMorphedModel($type) ?? $type;

            if (!class_exists($type)) {
                return false;
            }

            return resolve($type)->where('id', $value)->exists();
        });
    }
}
