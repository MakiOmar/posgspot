<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class Timezone
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Prefer APP_TIMEZONE from .env so stored and displayed datetimes match local wall-clock.
        $timezone = config('app.timezone_env', config('app.timezone'));

        if (empty($timezone)) {
            if (session()->has('business.time_zone')) {
                $timezone = $request->session()->get('business.time_zone');
            } elseif (Auth::check() && ! empty(Auth::user()->business)) {
                $timezone = Auth::user()->business->time_zone;
            }
        }

        if (! empty($timezone)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        return $next($request);
    }
}
