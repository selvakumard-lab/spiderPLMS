<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Workspace routes need a tenant. Regular users always have one; a Super Admin
 * must first "enter" a workspace (X-Tenant header).
 */
class EnsureTenantContext
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->check()) {
            return response()->json([
                'message' => 'Select a workspace first.',
                'code' => 'tenant_required',
            ], Response::HTTP_CONFLICT);
        }

        return $next($request);
    }
}
