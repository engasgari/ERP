<?php

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
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'fiscal.period' => \App\Http\Middleware\EnsureFiscalPeriodDatesAreValid::class,
            'active.erp' => \App\Http\Middleware\EnsureActiveApp::class.':erp',
            'active.crm' => \App\Http\Middleware\EnsureActiveApp::class.':crm',
        ]);
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('crm') || $request->is('crm/*')) {
                return route('crm.login');
            }

            return route('login');
        });
        $middleware->web(append: [
            \App\Http\Middleware\EnsureFiscalPeriodDatesAreValid::class,
            \App\Http\Middleware\HandleBreadcrumbContext::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\EnsureFiscalPeriodDatesAreValid::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
