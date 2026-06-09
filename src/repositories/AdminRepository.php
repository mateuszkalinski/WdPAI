<?php

require_once 'Repository.php';

class AdminRepository extends Repository
{
    public function getUsers(): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT *
            FROM admin_user_overview
            ORDER BY created_at DESC;
            "
        );
        $query->execute();

        return $query->fetchAll();
    }

    public function getUserById(int $userId): ?array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT u.id, u.username, u.email, r.name AS role, u.is_active
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.id = :id
            LIMIT 1;
            "
        );
        $query->bindValue(':id', $userId, PDO::PARAM_INT);
        $query->execute();

        $user = $query->fetch();
        return $user ?: null;
    }

    public function blockUser(int $userId, string $reason = 'Zablokowane przez administratora.'): void
    {
        $query = $this->database->connect()->prepare(
            "
            UPDATE users
            SET is_active = FALSE,
                blocked_at = CURRENT_TIMESTAMP,
                blocked_reason = :reason
            WHERE id = :id;
            "
        );
        $query->bindValue(':id', $userId, PDO::PARAM_INT);
        $query->bindValue(':reason', $reason, PDO::PARAM_STR);
        $query->execute();
    }

    public function unblockUser(int $userId): void
    {
        $query = $this->database->connect()->prepare(
            "
            UPDATE users
            SET is_active = TRUE,
                blocked_at = NULL,
                blocked_reason = NULL
            WHERE id = :id;
            "
        );
        $query->bindValue(':id', $userId, PDO::PARAM_INT);
        $query->execute();
    }

    public function deleteUser(int $userId): void
    {
        $query = $this->database->connect()->prepare(
            "
            DELETE FROM users
            WHERE id = :id;
            "
        );
        $query->bindValue(':id', $userId, PDO::PARAM_INT);
        $query->execute();
    }
}
