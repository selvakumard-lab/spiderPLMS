<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the current tenant for an authenticated API request.
 *
 * - Tenant users are always bound to their own tenant (X-Tenant is ignored),
 *   so a user can never reach another tenant's data by changing a header.
 * - Super admins may act inside a tenant by sending "X-Tenant: {slug}".
 */
class ResolveTenant
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $tenant = $user->isSuperAdmin()
            ? $this->tenantFromHeader($request)
            : Tenant::find($user->tenant_id);

        if (! $user->isSuperAdmin()) {
            if ($tenant === null) {
                return response()->json(['message' => 'Your account is not attached to a workspace.'], Response::HTTP_FORBIDDEN);
            }

            if (! $tenant->hasAccess()) {
                return response()->json([
                    'message' => 'This workspace is inactive. Please renew your subscription.',
                    'code' => 'tenant_inactive',
                ], Response::HTTP_PAYMENT_REQUIRED);
            }
        }

        $this->context->set($tenant);

        return $next($request);
    }

    private function tenantFromHeader(Request $request): ?Tenant
    {
        $slug = $request->header('X-Tenant');

        return $slug ? Tenant::whereRaw('lower(slug) = ?', [strtolower($slug)])->first() : null;
    }
}
