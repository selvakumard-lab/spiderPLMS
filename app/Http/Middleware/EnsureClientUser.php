<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The client portal is only for users linked to a client record. */
class EnsureClientUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isClient()) {
            return response()->json(['message' => 'The client portal is for client accounts.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
