<?php

require_once 'AppController.php';
require_once __DIR__.'/../services/AuthService.php';

class SecurityController extends AppController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
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

        $this->requireCsrf();

        $result = $this->authService->login($_POST, $_SESSION, $_SERVER);

        if (!$result['success']) {
            http_response_code($result['status']);
            $this->render('login', ['messages' => $result['message']]);
            return;
        }

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

        $this->requireCsrf();

        $result = $this->authService->register($_POST);

        if (!$result['success']) {
            http_response_code($result['status']);
            $this->render('register', ['messages' => $result['message']]);
            return;
        }

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
