<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * This file is used to serve the application when using PHP's built-in server.
 * It's a simple router that forwards all requests to the Laravel application.
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

require_once __DIR__ . '/index.php';

