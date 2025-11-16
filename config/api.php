<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for external API servers used by the application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Production Server Configuration
    |--------------------------------------------------------------------------
    |
    | The production API server URL for employee and location data.
    | This is the main server that handles all business logic API calls.
    |
    */

    'production' => [
        'url' => env('PYTHON_SERVER_URL', 'https://vansale-app.loca.lt'),
        'timeout' => env('API_TIMEOUT', 10),
        'verify_ssl' => env('API_VERIFY_SSL', false), // Set to false for localtunnel URLs
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Server Configuration
    |--------------------------------------------------------------------------
    |
    | The local server URL for device information (IP address and device name).
    | This server runs locally and provides device-specific information.
    |
    */

    'local' => [
        'url' => env('LOCAL_SERVER_URL', 'http://localhost:5001'),
        'timeout' => env('LOCAL_SERVER_TIMEOUT', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | Define the API endpoints used throughout the application.
    | These endpoints are relative to the production server URL.
    |
    */

    'endpoints' => [
        'employee' => '/api/user/{id}',
        'location' => '/api/location/{code}',
        'health' => '/api/health',
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Server Endpoints
    |--------------------------------------------------------------------------
    |
    | Define the local server API endpoints.
    | These endpoints are relative to the local server URL.
    |
    */

    'local_endpoints' => [
        'server_info' => '/api/server-info',
        'device_name' => '/api/device-name',
        'lan_ip' => '/api/lan-ip',
        'health' => '/health',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Default Options
    |--------------------------------------------------------------------------
    |
    | Default options for HTTP client requests.
    |
    */

    'http_options' => [
        'timeout' => env('API_TIMEOUT', 10),
        'verify' => env('API_VERIFY_SSL', false),
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for retrying failed API requests.
    |
    */

    'retry' => [
        'enabled' => env('API_RETRY_ENABLED', true),
        'max_attempts' => env('API_RETRY_MAX_ATTEMPTS', 3),
        'delay' => env('API_RETRY_DELAY', 1000), // milliseconds
    ],

];

