<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Injects the storefront business_id onto every Storefront API request.
 */
class ResolveStorefrontBusiness
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('storefront_business_id', (int) config('storefront.business_id'));

        return $next($request);
    }
}
