<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';

// TODO musimy zapewnic, ze utworzony 
// obiekt kontrollera ma tylko jedna instancję - SINGLETON

// TODO 2 /dashboard -- wszystkei dnae
// /dashboard/12234 -- wyciagnie nam jakis elemtn o wskaznaym ID 12234
// REGEX
class Routing {

    public static $routes = [
        "login" => [
            "controller" => "SecurityController",
            "action" => "login"
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
        "" => [
            "controller" => "SecurityController",
            "action" => "login"
        ],
        "register" => [
            "controller" => "SecurityController",
            "action" => "register"
        ],
    ];

    public static function run(string $path) {
        // TODO sprawdzać za pomoca array_key_exists
        switch($path) {
            case 'dashboard':
            case 'planer':   // <-- DODANE
            case 'session':  // <-- DODANE
            case 'atlas':    // <-- DODANE
            case 'history':  // <-- DODANE
            case '':
            case 'login':
            case 'register':
                $controller = Routing::$routes[$path]["controller"];
                $action = Routing::$routes[$path]["action"];

                $controllerObj = new $controller;
                $id = null;

                $controllerObj->$action($id);
                break; 
            default:
                include 'public/views/404.html';
                break;
        }
    }
}