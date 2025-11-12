<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Set error reporting for debugging
$debug = getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1';
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

try {
    // Determine if the application is in maintenance mode...
    if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
        require $maintenance;
    }

    // Register the Composer autoloader...
    require __DIR__.'/../vendor/autoload.php';

    // Bootstrap Laravel and handle the request...
    /** @var Application $app */
    $app = require_once __DIR__.'/../bootstrap/app.php';

    $request = Request::capture();
    $response = $app->handleRequest($request);
    $response->send();
    
} catch (\Throwable $e) {
    // Log error details for debugging
    $errorMessage = $e->getMessage();
    $errorFile = $e->getFile();
    $errorLine = $e->getLine();
    $errorTrace = $e->getTraceAsString();
    
    error_log('Laravel Error: ' . $errorMessage);
    error_log('File: ' . $errorFile . ':' . $errorLine);
    error_log('Trace: ' . $errorTrace);
    
    // Return detailed error in debug mode, generic in production
    http_response_code(500);
    
    if ($debug) {
        // Show detailed error for debugging
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>Error</title><style>body{font-family:monospace;padding:20px;background:#f5f5f5;}pre{background:#fff;padding:15px;border-radius:5px;overflow:auto;}</style></head><body>';
        echo '<h1>Laravel Error</h1>';
        echo '<h2>' . htmlspecialchars($errorMessage) . '</h2>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($errorFile) . ':' . $errorLine . '</p>';
        echo '<h3>Stack Trace:</h3>';
        echo '<pre>' . htmlspecialchars($errorTrace) . '</pre>';
        echo '</body></html>';
    } else {
        // Return JSON error for production
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => 'An error occurred while processing your request.',
        ]);
    }
    exit;
}

