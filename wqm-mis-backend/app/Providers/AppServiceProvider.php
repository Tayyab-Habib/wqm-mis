<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
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

        // ── Inject IP + user-agent into every Spatie Activity row ──────────
        // Spatie doesn't record request metadata by default. We tap the
        // `creating` model event so every new Activity row gets the current
        // request's IP and user-agent merged into its `properties` JSON.
        // The UsersHR Activity Trail surfaces `properties.ip` directly in
        // the IP Address column.
        Activity::creating(function (Activity $activity) {
            // Skip if there's no HTTP request in scope (artisan, queues, etc.)
            if (!app()->bound('request')) return;
            $request = app('request');
            if (!$request instanceof \Illuminate\Http\Request) return;

            $ip = $request->ip();
            $ua = (string) $request->header('User-Agent', '');
            $existing = $activity->properties;
            if ($existing instanceof \Illuminate\Support\Collection) {
                $existing = $existing->toArray();
            } elseif (!is_array($existing)) {
                $existing = [];
            }
            $activity->properties = array_merge($existing, [
                'ip'         => $ip,
                'user_agent' => $ua ?: null,
            ]);
        });
    }
}
