<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\RedirectNonAdminFromProfile;
use App\Http\Middleware\RequireScannerTerminal;
use App\Http\Middleware\ThrottleScannerRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'redirect.non-admin.profile' => RedirectNonAdminFromProfile::class,
            'active' => EnsureAccountIsActive::class,
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'scanner.terminal' => RequireScannerTerminal::class,
            'scanner.throttle' => ThrottleScannerRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson() || $request->is('api/*'),
        );
    })->create();
