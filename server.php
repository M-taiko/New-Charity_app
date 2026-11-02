<?php

// Custom router for `php artisan serve` (it prefers a project-root server.php).
// Rationale: for /app/<path> the built-in server resolves the script to
// public/app/index.html with PATH_INFO, and Symfony then computes a base URL
// of /app — stripping the prefix so Laravel sees /<path>. We therefore serve
// real files directly (correct MIME types from the built-in server) and push
// every /app deep link through the front controller with a clean SCRIPT_NAME.

$publicPath = getcwd();

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

// existing static files — including every built SPA asset under /app
if ($uri !== '/' && $uri !== '' && is_file($publicPath.$uri)) {
    return false;
}

$formattedDateTime = date('D M j H:i:s Y');
$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];
file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

// everything else — /app deep links included — goes through Laravel
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require_once $publicPath.'/index.php';
