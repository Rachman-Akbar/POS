<?php

use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsureUserHasPermission::class,
            'user.active' => EnsureUserIsActive::class,
        ]);

        // Tidak ada named route `login` di aplikasi ini: login adalah halaman
        // React Router. Tanpa ini, `auth:sanctum` untuk request non-JSON akan
        // melempar RouteNotFoundException (500), bukan 401.
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');

        // Permission dicek sebelum model binding. Kalau tidak, `POST
        // /api/orders/999/complete` membalas 404 untuk order yang tidak ada
        // dan 403 untuk yang ada, sehingga user tanpa izin masih bisa menebak
        // order mana yang benar-benar ada.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EnsureUserHasPermission::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
