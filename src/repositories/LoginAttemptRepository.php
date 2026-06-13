<?php

require_once 'Repository.php';

class LoginAttemptRepository extends Repository
{
    public function countRecentFailures(string $email, ?string $ipAddress, int $windowSeconds): int
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT COUNT(*)::INT
            FROM login_attempts
            WHERE attempted_at >= CURRENT_TIMESTAMP - (:window_seconds * INTERVAL '1 second')
              AND (
                LOWER(email) = LOWER(:email)
                OR (:has_ip = 1 AND ip_address = CAST(:ip_address AS INET))
              );
            "
        );
        $query->bindValue(':window_seconds', $windowSeconds, PDO::PARAM_INT);
        $query->bindValue(':email', $email, PDO::PARAM_STR);
        $query->bindValue(':has_ip', $ipAddress === null ? 0 : 1, PDO::PARAM_INT);
        $query->bindValue(':ip_address', $ipAddress, $ipAddress === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    public function recordFailure(string $email, ?string $ipAddress, string $reason): void
    {
        $query = $this->database->connect()->prepare(
            "
            INSERT INTO login_attempts (email, ip_address, reason)
            VALUES (:email, CAST(:ip_address AS INET), :reason);
            "
        );
        $query->bindValue(':email', $email, PDO::PARAM_STR);
        $query->bindValue(':ip_address', $ipAddress, $ipAddress === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $query->bindValue(':reason', $reason, PDO::PARAM_STR);
        $query->execute();
    }
}
