<?php

require_once __DIR__.'/../repositories/UsersRepository.php';
require_once __DIR__.'/../repositories/LoginAttemptRepository.php';

class AuthService
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCK_SECONDS = 900;
    private const MAX_EMAIL_LENGTH = 100;
    private const MAX_PASSWORD_LENGTH = 255;
    private const MAX_USERNAME_LENGTH = 50;
    private const MAX_NAME_LENGTH = 80;

    private UsersRepository $userRepository;
    private LoginAttemptRepository $loginAttemptRepository;

    public function __construct(?UsersRepository $userRepository = null, ?LoginAttemptRepository $loginAttemptRepository = null)
    {
        $this->userRepository = $userRepository ?? new UsersRepository();
        $this->loginAttemptRepository = $loginAttemptRepository ?? new LoginAttemptRepository();
    }

    public function login(array $input, array &$session, array $server): array
    {
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $ipAddress = $this->clientIp($server);
        $invalidMessage = 'E-mail lub haslo nieprawidlowe';

        if ($this->isLoginRateLimited($email, $ipAddress)) {
            $this->registerFailedLogin($email, $ipAddress, 'rate_limited', $server);
            return $this->failure(429, 'Za duzo nieudanych prob. Sprobuj ponownie za chwile.');
        }

        if (!$this->isLoginInputValid($email, $password)) {
            $this->registerFailedLogin($email, $ipAddress, 'invalid_input', $server);
            return $this->failure(401, $invalidMessage);
        }

        $user = $this->userRepository->getUserByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->registerFailedLogin($email, $ipAddress, 'invalid_credentials', $server);
            return $this->failure(401, $invalidMessage);
        }

        if (!$user['is_active']) {
            $this->registerFailedLogin($email, $ipAddress, 'blocked_account', $server);
            return $this->failure(403, 'Konto zostalo zablokowane');
        }

        session_regenerate_id(true);
        $this->resetLoginRateLimit($session);
        $this->fillSession($session, $user);
        $this->userRepository->updateLastLogin((int) $user['id']);

        return ['success' => true];
    }

    public function register(array $input): array
    {
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $password2 = (string) ($input['password2'] ?? '');
        $username = trim((string) ($input['username'] ?? ''));
        $firstname = trim((string) ($input['firstName'] ?? ''));
        $lastname = trim((string) ($input['lastName'] ?? ''));

        if ($email === '' || $password === '' || $password2 === '' || $username === '' || $firstname === '' || $lastname === '') {
            return $this->failure(400, 'Wypelnij wszystkie pola');
        }

        if (!$this->isRegisterLengthValid($email, $password, $password2, $username, $firstname, $lastname)) {
            return $this->failure(400, 'Przekroczono dozwolona dlugosc danych');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->failure(400, 'Podaj poprawny adres e-mail');
        }

        if ($password !== $password2) {
            return $this->failure(400, 'Hasla sie nie zgadzaja');
        }

        if (strlen($password) < 6) {
            return $this->failure(400, 'Haslo musi miec minimum 6 znakow');
        }

        if ($this->userRepository->getUserByEmail($email)) {
            return $this->failure(409, 'Konto z podanym e-mailem juz istnieje');
        }

        if ($this->userRepository->getUserByUsername($username)) {
            return $this->failure(409, 'Nazwa uzytkownika jest juz zajeta');
        }

        $this->userRepository->createUser(
            $username,
            $email,
            password_hash($password, PASSWORD_BCRYPT),
            $firstname,
            $lastname
        );

        return ['success' => true];
    }

    private function isLoginInputValid(string $email, string $password): bool
    {
        return $email !== ''
            && $password !== ''
            && strlen($email) <= self::MAX_EMAIL_LENGTH
            && strlen($password) <= self::MAX_PASSWORD_LENGTH
            && filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    private function isRegisterLengthValid(
        string $email,
        string $password,
        string $password2,
        string $username,
        string $firstname,
        string $lastname
    ): bool {
        return strlen($email) <= self::MAX_EMAIL_LENGTH
            && strlen($password) <= self::MAX_PASSWORD_LENGTH
            && strlen($password2) <= self::MAX_PASSWORD_LENGTH
            && strlen($username) <= self::MAX_USERNAME_LENGTH
            && strlen($firstname) <= self::MAX_NAME_LENGTH
            && strlen($lastname) <= self::MAX_NAME_LENGTH;
    }

    private function isLoginRateLimited(string $email, ?string $ipAddress): bool
    {
        try {
            return $this->loginAttemptRepository->countRecentFailures(
                $this->emailForAudit($email),
                $ipAddress,
                self::LOGIN_LOCK_SECONDS
            ) >= self::MAX_LOGIN_ATTEMPTS;
        } catch (Throwable) {
            return false;
        }
    }

    private function registerFailedLogin(string $email, ?string $ipAddress, string $reason, array $server): void
    {
        $auditEmail = $this->emailForAudit($email);

        try {
            $this->loginAttemptRepository->recordFailure($auditEmail, $ipAddress, $reason);
            $attempts = $this->loginAttemptRepository->countRecentFailures($auditEmail, $ipAddress, self::LOGIN_LOCK_SECONDS);
        } catch (Throwable) {
            $attempts = 0;
        }

        $this->logFailedLogin($email, $reason, $attempts, $server);
    }

    private function logFailedLogin(string $email, string $reason, int $attempts, array $server): void
    {
        error_log(sprintf(
            'Failed login for email=%s ip=%s reason=%s attempts=%d',
            $this->sanitizeEmailForLog($email),
            $server['REMOTE_ADDR'] ?? 'unknown',
            $reason,
            $attempts
        ));
    }

    private function sanitizeEmailForLog(string $email): string
    {
        $email = substr(trim($email), 0, self::MAX_EMAIL_LENGTH);
        $email = preg_replace('/[\r\n\t]+/', '_', $email);

        return $email !== '' ? $email : 'empty';
    }

    private function resetLoginRateLimit(array &$session): void
    {
        unset($session['failed_login_attempts'], $session['login_locked_until']);
    }

    private function fillSession(array &$session, array $user): void
    {
        $session['user_id'] = (int) $user['id'];
        $session['user_email'] = $user['email'];
        $session['username'] = $user['username'];
        $session['role'] = $user['role'];
        $session['firstname'] = $user['firstname'] ?? '';
        $session['lastname'] = $user['lastname'] ?? '';
    }

    private function failure(int $statusCode, string $message): array
    {
        return [
            'success' => false,
            'status' => $statusCode,
            'message' => $message
        ];
    }

    private function emailForAudit(string $email): string
    {
        $email = substr(trim($email), 0, self::MAX_EMAIL_LENGTH);
        $email = preg_replace('/[\r\n\t]+/', '_', $email);

        return $email !== '' ? $email : 'empty';
    }

    private function clientIp(array $server): ?string
    {
        $ipAddress = (string) ($server['REMOTE_ADDR'] ?? '');

        return filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : null;
    }
}
