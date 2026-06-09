<?php

require_once 'Repository.php';

class BadgeRepository extends Repository
{
    public function getBadges(): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                b.id,
                b.name,
                b.slug,
                b.description,
                b.icon,
                b.criteria_type,
                b.target_value,
                b.is_active,
                b.created_at,
                e.name AS exercise_name,
                mg.name AS muscle_group_name,
                u.username AS created_by,
                COUNT(ub.id) AS awarded_count
            FROM badges b
            LEFT JOIN exercises e ON e.id = b.exercise_id
            LEFT JOIN muscle_groups mg ON mg.id = b.muscle_group_id
            LEFT JOIN users u ON u.id = b.created_by_user_id
            LEFT JOIN user_badges ub ON ub.badge_id = b.id
            GROUP BY b.id, e.name, mg.name, u.username
            ORDER BY b.created_at DESC, b.name ASC;
            "
        );
        $query->execute();

        return $query->fetchAll();
    }

    public function slugExists(string $slug): bool
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT 1
            FROM badges
            WHERE slug = :slug
            LIMIT 1;
            "
        );
        $query->bindValue(':slug', $slug, PDO::PARAM_STR);
        $query->execute();

        return (bool) $query->fetchColumn();
    }

    public function createBadge(
        int $createdByUserId,
        string $name,
        string $slug,
        string $description,
        string $icon,
        string $criteriaType,
        float $targetValue,
        ?int $exerciseId,
        ?int $muscleGroupId
    ): int {
        $query = $this->database->connect()->prepare(
            "
            INSERT INTO badges (
                created_by_user_id,
                exercise_id,
                muscle_group_id,
                name,
                slug,
                description,
                icon,
                criteria_type,
                target_value
            )
            VALUES (
                :created_by_user_id,
                :exercise_id,
                :muscle_group_id,
                :name,
                :slug,
                :description,
                :icon,
                :criteria_type,
                :target_value
            )
            RETURNING id;
            "
        );
        $query->execute([
            ':created_by_user_id' => $createdByUserId,
            ':exercise_id' => $exerciseId,
            ':muscle_group_id' => $muscleGroupId,
            ':name' => $name,
            ':slug' => $slug,
            ':description' => $description,
            ':icon' => $icon,
            ':criteria_type' => $criteriaType,
            ':target_value' => $targetValue
        ]);

        return (int) $query->fetchColumn();
    }

    public function setBadgeActive(int $badgeId, bool $isActive): void
    {
        $query = $this->database->connect()->prepare(
            "
            UPDATE badges
            SET is_active = :is_active
            WHERE id = :id;
            "
        );
        $query->bindValue(':id', $badgeId, PDO::PARAM_INT);
        $query->bindValue(':is_active', $isActive, PDO::PARAM_BOOL);
        $query->execute();
    }
}
