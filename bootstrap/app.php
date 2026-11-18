<?php

use App\Http\Middleware\EnsureClientUser;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\ResolveTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'super-admin' => EnsureSuperAdmin::class,
            'tenant.required' => EnsureTenantContext::class,
            'staff' => EnsureStaff::class,
            'client' => EnsureClientUser::class,
        ]);

        // The tenant must be bound BEFORE route-model binding runs, otherwise
        // {project} / {task} would be resolved without the tenant scope.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Never reveal model class names (or whether a record exists in another tenant).
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }

            return null;
        });

        $exceptions->context(fn () => [
            'tenant_id' => app(TenantContext::class)->id(),
        ]);
    })->create();
