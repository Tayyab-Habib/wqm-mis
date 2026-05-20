<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

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

        // ── Stamp IP + user-agent on every Activity row ────────────────
        // Spatie's activity_log doesn't capture request metadata by default;
        // it only stores the model attribute diff in `properties`. We tap
        // into the `creating` lifecycle and merge `ip` + `user_agent` so the
        // Activity Trail modal in UsersHR can display them. Guarded against
        // CLI / queue contexts where there's no HTTP request bound.
        Activity::creating(function (Activity $activity) {
            if (! $this->app->bound('request')) {
                return;
            }
            $request = $this->app->make('request');
            if (! $request instanceof \Illuminate\Http\Request) {
                return;
            }

            // Properties may arrive as a Collection (Eloquent cast) or array
            // depending on how the caller built it. Normalize to a plain
            // array so the merge works regardless.
            $props = $activity->properties;
            if ($props instanceof \Illuminate\Support\Collection) {
                $props = $props->toArray();
            } elseif (! is_array($props)) {
                $props = [];
            }

            $props['ip']         = $request->ip();
            $props['user_agent'] = substr((string) $request->userAgent(), 0, 250);

            $activity->properties = $props;
        });
    }
}
