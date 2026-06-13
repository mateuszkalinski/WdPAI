<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    $cookieParams = session_get_cookie_params();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookieParams['path'] ?: '/',
        'domain' => $cookieParams['domain'] ?? '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

session_start();

require_once 'Routing.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if (PHP_SAPI === 'cli-server' && preg_match('/^\/public\/.+\.(?:css|js|svg|png|jpe?g|webp|ico|woff2?)$/i', $requestPath)) {
    $staticFile = realpath(__DIR__ . $requestPath);
    $publicDir = realpath(__DIR__ . '/public');

    if ($staticFile !== false && $publicDir !== false && str_starts_with($staticFile, $publicDir) && is_file($staticFile)) {
        return false;
    }
}

$path = trim($requestPath, '/');

Routing::run($path);


