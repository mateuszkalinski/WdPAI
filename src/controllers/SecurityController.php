<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class SecurityController extends AppController {

    private $userRepository;

    // TODO: Zrealizowane - inicjalizacja repozytorium raz w konstruktorze
    public function __construct()
    {
        // Jeśli AppController zyskałby konstruktor, warto dodać parent::__construct();
        $this->userRepository = new UsersRepository();
    }

    public function login() {
        // Jeśli ktoś już jest zalogowany, wyślij go od razu na dashboard
        if ($this->isLogged()) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/dashboard");
            exit();
        }

        if (!$this->isPost()) {
            return $this->render('login');
        }

        $email = trim($_POST["email"] ?? '');
        $password = $_POST["password"] ?? '';

        if (empty($email) || empty($password)) {
            return $this->render('login', ['messages' => 'Wypełnij wszystkie pola']);
        }

        $user = $this->userRepository->getUserByEmail($email);

        if (!$user) {
            return $this->render('login', ['messages' => 'Użytkownik nie istnieje']);
        }

        if (!password_verify($password, $user['password'])) {
            return $this->render('login', ['messages' => 'Nieprawidłowe hasło']);
        }

        // TODO: Zrealizowane - logowanie przy użyciu sesji
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/dashboard");
        exit(); // Zawsze dodawaj exit() po przekierowaniu
    }

    public function register() {
        // Jeśli ktoś już jest zalogowany, wyślij go od razu na dashboard
        if ($this->isLogged()) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/dashboard");
            exit();
        }
        
        if ($this->isPost()) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $password2 = $_POST['password2'] ?? '';
            $username = trim($_POST['username'] ?? ''); // Dodane pole username
            $firstname = trim($_POST['firstName'] ?? ''); // Pobierane z name="firstName" w Twoim HTML
            $lastname = trim($_POST['lastName'] ?? '');   // Pobierane z name="lastName" w Twoim HTML

            if (empty($email) || empty($password) || empty($username) || empty($firstname) || empty($lastname)) {
                return $this->render('register', ['messages' => 'Wypełnij wszystkie pola']);
            }

            // TODO: Zrealizowane - porównanie haseł
            if ($password !== $password2) {
                return $this->render('register', ['messages' => 'Hasła się nie zgadzają']);
            }

            $user = $this->userRepository->getUserByEmail($email);
            if ($user) {
                return $this->render("register", ["messages" => "Użytkownik o takim emailu już istnieje"]);
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            // Przekazujemy wszystkie dane do repozytorium
            $this->userRepository->createUser($username, $email, $hashedPassword, $firstname, $lastname);

            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit();
        }

        return $this->render("register");
    }
}