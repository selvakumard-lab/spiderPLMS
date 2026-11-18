<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');
        $tenant = app(TenantContext::class)->get()?->load('plan');

        return response()->json([
            'user' => new UserResource($user),
            'tenant' => $tenant ? new TenantResource($tenant) : null,
            'permissions' => $user->isSuperAdmin() ? ['*'] : $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function tokenResponse(User $user, ?Tenant $tenant, string $device, int $status = 200): JsonResponse
    {
        if ($tenant !== null) {
            app(TenantContext::class)->set($tenant);
        }

        $user->load('roles');

        return response()->json([
            'token' => $user->createToken($device)->plainTextToken,
            'user' => new UserResource($user),
            'tenant' => $tenant ? new TenantResource($tenant->load('plan')) : null,
            'permissions' => $user->isSuperAdmin() ? ['*'] : $user->getAllPermissions()->pluck('name'),
        ], $status);
    }
}
