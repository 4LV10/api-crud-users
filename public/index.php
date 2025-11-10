<?php
spl_autoload_register(function($c) {
    $p = __DIR__ . '/../';
    $c = str_replace('\\', '/', $c);
    $paths = ["{$p}src/{$c}.php", "{$p}{$c}.php"];
    foreach ($paths as $f) {
        if (file_exists($f)) require $f;
    }
});

$cfg = require __DIR__ . '/../config/env.php';

use Src\Helpers\Response;
use Src\Middlewares\CorsMiddleware;

// CORS preflight early (OPTIONS)
CorsMiddleware::handle($cfg);
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri = strtok($_SERVER['REQUEST_URI'], '?');
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$path = '/' . trim(str_replace($base, '', $uri), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Routes map
$routes = [
    ['GET', '/api/v1/health', 'Src\\Controllers\\HealthController@show'],
    ['POST', '/api/v1/auth/login', 'Src\\Controllers\\AuthController@login'],
    ['GET', '/api/v1/users', 'Src\\Controllers\\UserController@index'],
    ['GET', '/api/v1/users/{id}', 'Src\\Controllers\\UserController@show'],
    ['POST', '/api/v1/users', 'Src\\Controllers\\UserController@store'],
    ['PUT', '/api/v1/users/{id}', 'Src\\Controllers\\UserController@update'],
    ['DELETE', '/api/v1/users/{id}', 'Src\\Controllers\\UserController@destroy'],
    ['POST', '/api/v1/upload', 'Src\\Controllers\\UploadController@store'],
    ['GET', '/api/v1/version', 'Src\\Controllers\\VersionController@show']
];

function matchRoute($routes, $method, $path) {
    foreach ($routes as $r) {
        [$m, $p, $h] = $r;
        if ($m !== $method) continue;
        $regex = preg_replace('#\{[^/]+\}#', '([\\w-]+)', $p);
        if (preg_match('#^' . $regex . '$#', $path, $mch)) {
            array_shift($mch);
            return [$h, $mch];
        }
    }
    return [null, null];
}

[$handler, $params] = matchRoute($routes, $method, $path);

if (!$handler) {
    Response::jsonError(404, 'Route not found');
}

[$class, $action] = explode('@', $handler);

if (!method_exists($class, $action)) {
    Response::jsonError(405, 'Method not allowed');
}

call_user_func_array([new $class($cfg), $action], $params);
