<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class SecurityController extends AppController
{
    private UsersRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UsersRepository();
    }

    public function login(): void
    {
        if ($this->isLogged()) {
            $this->redirect('/dashboard');
        }

        if (!$this->isPost()) {
            $this->render('login');
            return;
        }

        $email = trim($_POST["email"] ?? '');
        $password = $_POST["password"] ?? '';
        $invalidMessage = 'E-mail lub haslo nieprawidlowe';

        if (empty($email) || empty($password)) {
            $this->render('login', ['messages' => $invalidMessage]);
            return;
        }

        $user = $this->userRepository->getUserByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->render('login', ['messages' => $invalidMessage]);
            return;
        }

        if (!$user['is_active']) {
            $this->render('login', ['messages' => 'Konto zostalo zablokowane']);
            return;
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['firstname'] = $user['firstname'] ?? '';
        $_SESSION['lastname'] = $user['lastname'] ?? '';

        $this->userRepository->updateLastLogin((int) $user['id']);
        $this->redirect('/dashboard');
    }

    public function register(): void
    {
        if ($this->isLogged()) {
            $this->redirect('/dashboard');
        }

        if (!$this->isPost()) {
            $this->render('register');
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $firstname = trim($_POST['firstName'] ?? '');
        $lastname = trim($_POST['lastName'] ?? '');

        if (empty($email) || empty($password) || empty($password2) || empty($username) || empty($firstname) || empty($lastname)) {
            $this->render('register', ['messages' => 'Wypelnij wszystkie pola']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('register', ['messages' => 'Podaj poprawny adres e-mail']);
            return;
        }

        if ($password !== $password2) {
            $this->render('register', ['messages' => 'Hasla sie nie zgadzaja']);
            return;
        }

        if (strlen($password) < 6) {
            $this->render('register', ['messages' => 'Haslo musi miec minimum 6 znakow']);
            return;
        }

        if ($this->userRepository->getUserByEmail($email)) {
            $this->render('register', ['messages' => 'Konto z podanym e-mailem juz istnieje']);
            return;
        }

        if ($this->userRepository->getUserByUsername($username)) {
            $this->render('register', ['messages' => 'Nazwa uzytkownika jest juz zajeta']);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $this->userRepository->createUser($username, $email, $hashedPassword, $firstname, $lastname);

        $this->redirect('/login');
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        $this->redirect('/login');
    }
}
