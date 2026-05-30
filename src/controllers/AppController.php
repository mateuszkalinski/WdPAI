<?php


class AppController {
    protected function isGet(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'GET';
    }

    protected function isPost(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'POST';
    }

    // Sprawdza, czy w sesji istnieje ID użytkownika
    protected function isLogged(): bool
    {
        return isset($_SESSION['user_id']);
    }

    // Metoda "bramkarz" - wyrzuca na stronę logowania, jeśli ktoś nie ma dostępu
    protected function requireLogin()
    {
        if (!$this->isLogged()) {
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
            exit(); // exit() jest tu kluczowe, przerywa ładowanie reszty strony!
        }
    }
 
    protected function render(string $template = null, array $variables = [])
    {
        $templatePath = 'public/views/'. $template.'.html';
        $templatePath404 = 'public/views/404.html';
        $output = "";
                 
        if(file_exists($templatePath)){
            extract($variables);
            // ["tab_name" => $title]

            // $tab_name = $title

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