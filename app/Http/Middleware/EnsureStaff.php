<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Workspace management screens are for the team; client portal users are sent to their own area. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isClient()) {
            return response()->json(['message' => 'This area is for the delivery team.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
