<?php

require_once 'Repository.php';

class UsersRepository extends Repository
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

    public function getUserByEmail(string $email): ?array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                u.id,
                u.username,
                u.email,
                u.password,
                u.is_active,
                u.blocked_at,
                u.blocked_reason,
                r.name AS role,
                p.firstname,
                p.lastname
            FROM users u
            JOIN roles r ON r.id = u.role_id
            LEFT JOIN user_profiles p ON p.user_id = u.id
            WHERE LOWER(u.email) = LOWER(:email)
            LIMIT 1;
            "
        );
        $query->bindValue(':email', $email, PDO::PARAM_STR);
        $query->execute();

        $user = $query->fetch();
        return $user ?: null;
    }

    public function getUserByUsername(string $username): ?array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT id, username
            FROM users
            WHERE LOWER(username) = LOWER(:username)
            LIMIT 1;
            "
        );
        $query->bindValue(':username', $username, PDO::PARAM_STR);
        $query->execute();

        $user = $query->fetch();
        return $user ?: null;
    }

    public function createUser(
        string $username,
        string $email,
        string $hashedPassword,
        string $firstname,
        string $lastname,
        string $bio = ''
    ): int {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();

            $roleQuery = $connection->prepare(
                "
                SELECT id
                FROM roles
                WHERE name = 'user'
                LIMIT 1;
                "
            );
            $roleQuery->execute();
            $roleId = $roleQuery->fetchColumn();

            if (!$roleId) {
                throw new RuntimeException("Default user role does not exist");
            }

            $userQuery = $connection->prepare(
                "
                INSERT INTO users (role_id, username, email, password)
                VALUES (:role_id, :username, :email, :password)
                RETURNING id;
                "
            );
            $userQuery->execute([
                ':role_id' => (int) $roleId,
                ':username' => $username,
                ':email' => $email,
                ':password' => $hashedPassword
            ]);

            $userId = (int) $userQuery->fetchColumn();

            $profileQuery = $connection->prepare(
                "
                INSERT INTO user_profiles (user_id, firstname, lastname, bio)
                VALUES (:user_id, :firstname, :lastname, :bio);
                "
            );
            $profileQuery->execute([
                ':user_id' => $userId,
                ':firstname' => $firstname,
                ':lastname' => $lastname,
                ':bio' => $bio
            ]);

            $connection->commit();
            return $userId;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function updateLastLogin(int $userId): void
    {
        $query = $this->database->connect()->prepare(
            "
            UPDATE users
            SET last_login_at = CURRENT_TIMESTAMP
            WHERE id = :id;
            "
        );
        $query->bindValue(':id', $userId, PDO::PARAM_INT);
        $query->execute();
    }
}
