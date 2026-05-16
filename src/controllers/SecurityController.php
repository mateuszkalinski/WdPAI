<?php

require_once 'AppController.php';

class SecurityController extends AppController {

    public function login() {
        if ($this->isPost()) {
            // Tutaj w przyszłości sprawdzisz, czy user z $_POST['email'] 
            // istnieje w bazie i czy hasło się zgadza.
            
            // var_dump($_POST); <-- TO MUSI BYĆ USUNIĘTE LUB ZAKOMENTOWANE

            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/dashboard");
            exit(); // Dobrą praktyką jest dodanie exit() po przekierowaniu, aby zatrzymać dalsze wykonywanie skryptu
        }

        return $this->render("login");
    }

    public function register() {
        if ($this->isPost()) {
            // Tutaj w przyszłości dodasz kod rejestracji (haszowanie hasła i insert do bazy)
            
            // Po udanej rejestracji warto przekierować użytkownika np. na stronę logowania:
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit();
        }

        return $this->render("register");
    }
}