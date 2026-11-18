<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks a route unless the workspace's purchased plan includes the named capability, e.g. `plan.feature:whatsapp_chat`. */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = app(TenantContext::class)->get();

        if ($tenant === null || ! $tenant->planAllows($feature)) {
            return response()->json(['message' => 'This feature is not included in your current plan. Upgrade to use it.', 'feature' => $feature], Response::HTTP_PAYMENT_REQUIRED);
        }

        return $next($request);
    }
}
