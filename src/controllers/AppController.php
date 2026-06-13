<?php

class AppController
{
    protected function isGet(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'GET';
    }

    protected function isPost(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'POST';
    }

    protected function isLogged(): bool
    {
        return isset($_SESSION['user_id']);
    }

    protected function requireLogin(): void
    {
        if (!$this->isLogged()) {
            $this->redirect('/login');
        }
    }

    protected function requireRole(string $role): void
    {
        $this->requireLogin();

        if (($_SESSION['role'] ?? null) !== $role) {
            http_response_code(403);
            $this->render('403');
            exit();
        }
    }

    protected function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    protected function isValidCsrfToken(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    protected function requireCsrf(): void
    {
        $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        if (!$this->isValidCsrfToken($token)) {
            http_response_code(400);
            $this->render('400');
            exit();
        }
    }

    protected function redirect(string $path): void
    {
        header("Location: {$path}");
        exit();
    }

    protected function render(string $template = null, array $variables = []): void
    {
        $templatePath = 'public/views/'. $template.'.html';
        $templatePath404 = 'public/views/404.html';
        $output = "";
        $variables['csrfToken'] = $variables['csrfToken'] ?? $this->getCsrfToken();

        if (file_exists($templatePath)) {
            extract($variables);

            ob_start();
            include $templatePath;
            $output = ob_get_clean();
        } else {
            ob_start();
            include $templatePath404;
            $output = ob_get_clean();
        }

        echo $output;
    }
}
