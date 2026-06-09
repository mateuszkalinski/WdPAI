<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class DashboardController extends AppController
{
    public function index(): void
    {
        $this->requireLogin();

        $usersRepository = new UsersRepository();
        $users = $usersRepository->getUsers();

        $this->render("dashboard", [
            "title" => "Dashboard",
            "users" => $users,
            "activeTab" => "dashboard"
        ]);
    }

    public function planer(): void
    {
        $this->requireLogin();
        $this->render("planer", ["activeTab" => "planer"]);
    }

    public function session(): void
    {
        $this->requireLogin();
        $this->render("session", ["activeTab" => "session"]);
    }

    public function atlas(): void
    {
        $this->requireLogin();
        $this->render("atlas", ["activeTab" => "atlas"]);
    }

    public function history(): void
    {
        $this->requireLogin();
        $this->render("history", ["activeTab" => "history"]);
    }
}
