<?php
/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * This file is the entry point for the application.
 * It redirects requests to the public/index.php file when using a web server
 * that doesn't support mod_rewrite (or when .htaccess is not working properly)
 */

// Determine if the application is accessing from the public directory directly
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Remove the base path from the URI
if (strpos($uri, '/New-Charity_app') === 0) {
    $uri = substr($uri, strlen('/New-Charity_app'));
}

// Check if the requested resource exists in the public directory
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

// Otherwise, serve the application through public/index.php
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/public/index.php';
require_once __DIR__.'/public/index.php';
