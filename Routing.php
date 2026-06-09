<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';

class Routing
{
    public static array $routes = [
        "" => [
            "controller" => "SecurityController",
            "action" => "login"
        ],
        "login" => [
            "controller" => "SecurityController",
            "action" => "login"
        ],
        "logout" => [
            "controller" => "SecurityController",
            "action" => "logout"
        ],
        "register" => [
            "controller" => "SecurityController",
            "action" => "register"
        ],
        "dashboard" => [
            "controller" => "DashboardController",
            "action" => "index"
        ],
        "planer" => [
            "controller" => "DashboardController",
            "action" => "planer"
        ],
        "session" => [
            "controller" => "DashboardController",
            "action" => "session"
        ],
        "atlas" => [
            "controller" => "DashboardController",
            "action" => "atlas"
        ],
        "history" => [
            "controller" => "DashboardController",
            "action" => "history"
        ],
    ];

    public static function run(string $path): void
    {
        if (!array_key_exists($path, self::$routes)) {
            http_response_code(404);
            include 'public/views/404.html';
            return;
        }

        $controller = self::$routes[$path]["controller"];
        $action = self::$routes[$path]["action"];

        $controllerObj = new $controller;
        $controllerObj->$action();
    }
}
