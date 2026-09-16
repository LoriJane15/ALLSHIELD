<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);

        /*
         * An already-signed-in user hitting /login must land on their own area.
         * Laravel's default looks for a route named "dashboard" or "home"; this
         * app has admin.dashboard, lgu.dashboard and so on but none named
         * plainly "dashboard", so the fallback sent them to "/" — the public
         * landing page — which looked like the Log in button doing nothing.
         */
        \Illuminate\Auth\Middleware\RedirectIfAuthenticated::redirectUsing(
            fn ($request) => $request->user()
                ? route($request->user()->homeRoute())
                : '/'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * A stale CSRF token (session expired, or a login form restored from the
         * browser's back-forward cache) throws a 419. Rather than dead-ending on
         * the "Session expired" page, bounce back to the form — which now carries
         * a fresh token — so the next submit just works.
         *
         * Laravel converts TokenMismatchException to a 419 HttpException before
         * render callbacks run, so match the status code, not the original type.
         */
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null; // fall through to the default handler
            }

            if ($request->is('login')) {
                return redirect()->route('login')
                    ->withInput($request->except('password', '_token'))
                    ->with('status', 'Your session refreshed — please sign in again.');
            }

            return redirect()->back()
                ->withInput($request->except('_token'))
                ->with('status', 'Your session refreshed — please try again.');
        });
    })->create();
