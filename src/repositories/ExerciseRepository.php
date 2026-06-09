<?php

require_once 'Repository.php';

class ExerciseRepository extends Repository
{
    private const EXERCISE_LIST_SELECT = "
        SELECT
            e.id,
            e.name,
            e.slug,
            e.description,
            e.technique_notes,
            e.difficulty,
            e.video_url,
            e.is_active,
            e.created_at,
            eq.name AS equipment,
            COALESCE(STRING_AGG(DISTINCT mg.name, ', ' ORDER BY mg.name), '') AS muscle_groups,
            COALESCE(
                MIN(mg.name) FILTER (WHERE emg.involvement = 'primary'),
                MIN(mg.name),
                'Ogólne'
            ) AS primary_muscle_group
        FROM exercises e
        LEFT JOIN equipment eq ON eq.id = e.equipment_id
        LEFT JOIN exercise_muscle_groups emg ON emg.exercise_id = e.id
        LEFT JOIN muscle_groups mg ON mg.id = emg.muscle_group_id
    ";

    public function getExercises(): array
    {
        $query = $this->database->connect()->prepare(
            "
            ".self::EXERCISE_LIST_SELECT."
            GROUP BY e.id, eq.name
            ORDER BY e.created_at DESC, e.name ASC;
            "
        );
        $query->execute();

        return $query->fetchAll();
    }

    public function getActiveExercises(): array
    {
        return $this->searchExercises('', null);
    }

    public function searchExercises(string $search = '', ?int $muscleGroupId = null): array
    {
        $conditions = ["e.is_active = TRUE"];
        $params = [];

        if ($search !== '') {
            $conditions[] = "
                (
                    e.name ILIKE :search
                    OR e.description ILIKE :search
                    OR eq.name ILIKE :search
                    OR EXISTS (
                        SELECT 1
                        FROM exercise_muscle_groups emg_search
                        JOIN muscle_groups mg_search ON mg_search.id = emg_search.muscle_group_id
                        WHERE emg_search.exercise_id = e.id
                          AND mg_search.name ILIKE :search
                    )
                )
            ";
            $params[':search'] = '%'.$search.'%';
        }

        if ($muscleGroupId !== null) {
            $conditions[] = "
                EXISTS (
                    SELECT 1
                    FROM exercise_muscle_groups emg_filter
                    WHERE emg_filter.exercise_id = e.id
                      AND emg_filter.muscle_group_id = :muscle_group_id
                )
            ";
            $params[':muscle_group_id'] = $muscleGroupId;
        }

        $query = $this->database->connect()->prepare(
            "
            ".self::EXERCISE_LIST_SELECT."
            WHERE ".implode(' AND ', $conditions)."
            GROUP BY e.id, eq.name
            ORDER BY e.name ASC
            LIMIT 60;
            "
        );

        foreach ($params as $name => $value) {
            $type = $name === ':muscle_group_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
            $query->bindValue($name, $value, $type);
        }

        $query->execute();
        return $query->fetchAll();
    }

    public function getEquipment(): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT id, name
            FROM equipment
            ORDER BY name ASC;
            "
        );
        $query->execute();

        return $query->fetchAll();
    }

    public function getMuscleGroups(): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT id, name
            FROM muscle_groups
            ORDER BY name ASC;
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
            FROM exercises
            WHERE slug = :slug
            LIMIT 1;
            "
        );
        $query->bindValue(':slug', $slug, PDO::PARAM_STR);
        $query->execute();

        return (bool) $query->fetchColumn();
    }

    public function createExercise(
        string $name,
        string $slug,
        string $description,
        ?string $techniqueNotes,
        string $difficulty,
        ?string $videoUrl,
        ?int $equipmentId,
        array $muscleGroupIds
    ): int {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();

            $exerciseQuery = $connection->prepare(
                "
                INSERT INTO exercises (equipment_id, name, slug, description, technique_notes, difficulty, video_url)
                VALUES (:equipment_id, :name, :slug, :description, :technique_notes, :difficulty, :video_url)
                RETURNING id;
                "
            );
            $exerciseQuery->execute([
                ':equipment_id' => $equipmentId,
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $description,
                ':technique_notes' => $techniqueNotes,
                ':difficulty' => $difficulty,
                ':video_url' => $videoUrl
            ]);

            $exerciseId = (int) $exerciseQuery->fetchColumn();

            $muscleQuery = $connection->prepare(
                "
                INSERT INTO exercise_muscle_groups (exercise_id, muscle_group_id, involvement)
                VALUES (:exercise_id, :muscle_group_id, :involvement);
                "
            );

            foreach (array_values($muscleGroupIds) as $index => $muscleGroupId) {
                $muscleQuery->execute([
                    ':exercise_id' => $exerciseId,
                    ':muscle_group_id' => (int) $muscleGroupId,
                    ':involvement' => $index === 0 ? 'primary' : 'secondary'
                ]);
            }

            $connection->commit();
            return $exerciseId;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function setExerciseActive(int $exerciseId, bool $isActive): void
    {
        $query = $this->database->connect()->prepare(
            "
            UPDATE exercises
            SET is_active = :is_active
            WHERE id = :id;
            "
        );
        $query->bindValue(':id', $exerciseId, PDO::PARAM_INT);
        $query->bindValue(':is_active', $isActive, PDO::PARAM_BOOL);
        $query->execute();
    }
}
