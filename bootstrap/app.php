<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxy headers for Vercel/cloud platforms
        $middleware->trustProxies(at: '*');
        
        // Force HTTPS in production
        $middleware->web(prepend: [
            function (Request $request, \Closure $next) {
                // Redirect HTTP to HTTPS in production
                if ((app()->environment('production') || app()->environment('staging')) 
                    && !$request->secure() 
                    && !$request->is('health') 
                    && !$request->is('up')) {
                    return redirect()->secure($request->getRequestUri(), 301);
                }
                return $next($request);
            },
        ]);
        
        $middleware->web(append: [
            function (Request $request, \Closure $next) {
                // Force HTTPS scheme in production (Vercel provides HTTPS)
                if (app()->environment('production') || app()->environment('staging')) {
                    URL::forceScheme('https');
                }
                return $next($request);
            },
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
