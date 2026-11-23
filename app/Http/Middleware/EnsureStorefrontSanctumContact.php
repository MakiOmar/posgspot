<?php

namespace App\Http\Middleware;

use App\Contact;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After auth:sanctum on storefront routes: require an active Contact for this business.
 */
class EnsureStorefrontSanctumContact
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof Contact) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (($user->contact_status ?? 'active') !== 'active') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! in_array($user->type, ['customer', 'both'], true)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $businessId = (int) ($request->attributes->get('storefront_business_id')
            ?? config('storefront.business_id'));
        if ($businessId > 0 && (int) $user->business_id !== $businessId) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
