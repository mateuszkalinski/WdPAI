<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class DashboardController extends AppController {

    public function index() {
        $this->requireLogin(); // <-- ZABEZPIECZENIE
        
        $title = "DASHBOARD";
        $usersRepository = new UsersRepository();
        $users = $usersRepository->getUsers();
        // Dodajemy "activeTab" => "dashboard"
        return $this->render("dashboard", ["title" => $title, "users" => $users, "activeTab" => "dashboard"]);
    }

    public function planer() {
        // Przekazujemy informację, że aktywny jest planer
        return $this->render("planer", ["activeTab" => "planer"]);
    }

    public function session() {
        return $this->render("session", ["activeTab" => "session"]);
    }

    public function atlas() {
        return $this->render("atlas", ["activeTab" => "atlas"]);
    }

    public function history() {
        return $this->render("history", ["activeTab" => "history"]);
    }
}