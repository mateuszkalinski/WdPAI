<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';
require_once 'src/controllers/AdminController.php';
require_once 'src/controllers/ApiController.php';

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
        "planer/activate" => [
            "controller" => "DashboardController",
            "action" => "activatePlan"
        ],
        "planer/create" => [
            "controller" => "DashboardController",
            "action" => "createPlan"
        ],
        "planer/edit" => [
            "controller" => "DashboardController",
            "action" => "editActivePlan"
        ],
        "planer/delete" => [
            "controller" => "DashboardController",
            "action" => "deletePlan"
        ],
        "planer/exercises/add" => [
            "controller" => "DashboardController",
            "action" => "addPlanExercise"
        ],
        "planer/exercises/update" => [
            "controller" => "DashboardController",
            "action" => "updatePlanExercise"
        ],
        "planer/exercises/move" => [
            "controller" => "DashboardController",
            "action" => "movePlanExercise"
        ],
        "planer/exercises/delete" => [
            "controller" => "DashboardController",
            "action" => "deletePlanExercise"
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
        "admin/users" => [
            "controller" => "AdminController",
            "action" => "users"
        ],
        "admin/users/block" => [
            "controller" => "AdminController",
            "action" => "blockUser"
        ],
        "admin/users/unblock" => [
            "controller" => "AdminController",
            "action" => "unblockUser"
        ],
        "admin/users/delete" => [
            "controller" => "AdminController",
            "action" => "deleteUser"
        ],
        "admin/exercises" => [
            "controller" => "AdminController",
            "action" => "exercises"
        ],
        "admin/exercises/create" => [
            "controller" => "AdminController",
            "action" => "createExercise"
        ],
        "admin/exercises/deactivate" => [
            "controller" => "AdminController",
            "action" => "deactivateExercise"
        ],
        "admin/exercises/activate" => [
            "controller" => "AdminController",
            "action" => "activateExercise"
        ],
        "admin/badges" => [
            "controller" => "AdminController",
            "action" => "badges"
        ],
        "admin/badges/create" => [
            "controller" => "AdminController",
            "action" => "createBadge"
        ],
        "admin/badges/deactivate" => [
            "controller" => "AdminController",
            "action" => "deactivateBadge"
        ],
        "admin/badges/activate" => [
            "controller" => "AdminController",
            "action" => "activateBadge"
        ],
        "api/exercises/search" => [
            "controller" => "ApiController",
            "action" => "searchExercises"
        ],
        "api/workout/start" => [
            "controller" => "ApiController",
            "action" => "startWorkoutSession"
        ],
        "api/workout/set" => [
            "controller" => "ApiController",
            "action" => "addWorkoutSet"
        ],
        "api/workout/skip" => [
            "controller" => "ApiController",
            "action" => "skipWorkoutPlanItem"
        ],
        "api/workout/finish" => [
            "controller" => "ApiController",
            "action" => "finishWorkoutSession"
        ],
    ];

    public static function run(string $path): void
    {
        if (!array_key_exists($path, self::$routes)) {
            http_response_code(404);
            include 'public/views/404.html';
            return;
        }

        try {
            $controller = self::$routes[$path]["controller"];
            $action = self::$routes[$path]["action"];

            $controllerObj = new $controller;
            $controllerObj->$action();
        } catch (Throwable) {
            http_response_code(500);
            include 'public/views/500.html';
        }
    }
}
