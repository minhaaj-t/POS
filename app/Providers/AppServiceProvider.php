<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS URLs when the request is secure (HTTPS)
        // This ensures all route() and url() helpers generate HTTPS URLs
        // Handles both direct HTTPS and proxy/load balancer scenarios
        $isSecure = request()->isSecure() 
            || request()->header('X-Forwarded-Proto') === 'https'
            || request()->server('HTTP_X_FORWARDED_PROTO') === 'https'
            || env('FORCE_HTTPS', false);
            
        if ($isSecure) {
            URL::forceScheme('https');
        }
    }
}
