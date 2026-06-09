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

    protected function redirect(string $path): void
    {
        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}{$path}");
        exit();
    }

    protected function render(string $template = null, array $variables = []): void
    {
        $templatePath = 'public/views/'. $template.'.html';
        $templatePath404 = 'public/views/404.html';
        $output = "";

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
