<?php

namespace App\Http\Middleware;

use App\Support\StorefrontLocale;
use Closure;
use Illuminate\Http\Request;

class ResolveStorefrontContentLocale
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('storefront_content_locale', StorefrontLocale::resolve($request));

        return $next($request);
    }
}
