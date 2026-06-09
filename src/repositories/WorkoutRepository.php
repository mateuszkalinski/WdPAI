<?php

require_once 'Repository.php';

class WorkoutRepository extends Repository
{
    public function getActiveSession(int $userId): ?array
    {
        $connection = $this->database->connect();
        $sessionRow = $this->findActiveSessionRow($connection, $userId);

        if (!$sessionRow) {
            return null;
        }

        return $this->getSessionById($userId, (int) $sessionRow['id']);
    }

    public function startSession(int $userId): array
    {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();

            $sessionRow = $this->findActiveSessionRow($connection, $userId, true);
            $sessionId = $sessionRow ? (int) $sessionRow['id'] : $this->createSession($connection, $userId);
            $session = $this->getSessionByIdUsingConnection($connection, $userId, $sessionId);

            $connection->commit();
            return $session;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function addSet(
        int $userId,
        int $exerciseId,
        float $weightKg,
        int $reps,
        ?float $rpe,
        string $setType,
        ?string $note
    ): array {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();

            $sessionRow = $this->findActiveSessionRow($connection, $userId, true);
            $sessionId = $sessionRow ? (int) $sessionRow['id'] : $this->createSession($connection, $userId);

            $exercise = $this->getActiveExercise($connection, $exerciseId);
            if (!$exercise) {
                throw new InvalidArgumentException('Wybrane cwiczenie nie istnieje albo jest nieaktywne.');
            }

            $orderQuery = $connection->prepare(
                "
                SELECT COALESCE(MAX(set_order), 0) + 1
                FROM performed_sets
                WHERE workout_session_id = :session_id
                  AND exercise_id = :exercise_id;
                "
            );
            $orderQuery->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
            $orderQuery->bindValue(':exercise_id', $exerciseId, PDO::PARAM_INT);
            $orderQuery->execute();
            $setOrder = (int) $orderQuery->fetchColumn();

            $insertQuery = $connection->prepare(
                "
                INSERT INTO performed_sets (
                    workout_session_id,
                    exercise_id,
                    set_order,
                    set_type,
                    weight_kg,
                    reps,
                    rpe,
                    note
                )
                VALUES (
                    :workout_session_id,
                    :exercise_id,
                    :set_order,
                    :set_type,
                    :weight_kg,
                    :reps,
                    :rpe,
                    :note
                )
                RETURNING
                    id,
                    workout_session_id,
                    exercise_id,
                    set_order,
                    set_type,
                    weight_kg,
                    reps,
                    rpe,
                    note,
                    performed_at;
                "
            );
            $insertQuery->bindValue(':workout_session_id', $sessionId, PDO::PARAM_INT);
            $insertQuery->bindValue(':exercise_id', $exerciseId, PDO::PARAM_INT);
            $insertQuery->bindValue(':set_order', $setOrder, PDO::PARAM_INT);
            $insertQuery->bindValue(':set_type', $setType, PDO::PARAM_STR);
            $insertQuery->bindValue(':weight_kg', (string) $weightKg, PDO::PARAM_STR);
            $insertQuery->bindValue(':reps', $reps, PDO::PARAM_INT);
            $insertQuery->bindValue(':rpe', $rpe === null ? null : (string) $rpe, $rpe === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $insertQuery->bindValue(':note', $note, $note === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $insertQuery->execute();
            $set = $insertQuery->fetch();

            $session = $this->getSessionByIdUsingConnection($connection, $userId, $sessionId);

            $connection->commit();

            return [
                'session' => $session,
                'set' => $set,
                'sets' => $this->getSetsForSession($userId, $sessionId)
            ];
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function finishActiveSession(int $userId, ?float $sessionRpe, ?string $notes): ?array
    {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();

            $sessionRow = $this->findActiveSessionRow($connection, $userId, true);
            if (!$sessionRow) {
                $connection->commit();
                return null;
            }

            $sessionId = (int) $sessionRow['id'];
            $query = $connection->prepare(
                "
                UPDATE workout_sessions
                SET
                    status = 'finished',
                    finished_at = CURRENT_TIMESTAMP,
                    session_rpe = :session_rpe,
                    notes = COALESCE(:notes, notes)
                WHERE id = :session_id
                  AND user_id = :user_id;
                "
            );
            $query->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
            $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $query->bindValue(':session_rpe', $sessionRpe === null ? null : (string) $sessionRpe, $sessionRpe === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $query->bindValue(':notes', $notes, $notes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $query->execute();

            $session = $this->getSessionByIdUsingConnection($connection, $userId, $sessionId);

            $connection->commit();

            return [
                'session' => $session,
                'sets' => $this->getSetsForSession($userId, $sessionId)
            ];
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function getSetsForSession(int $userId, int $sessionId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                ps.id,
                ps.workout_session_id,
                ps.exercise_id,
                ps.set_order,
                ps.set_type,
                ps.weight_kg,
                ps.reps,
                ps.rpe,
                ps.note,
                ps.performed_at,
                (ps.weight_kg * ps.reps)::NUMERIC(12,2) AS volume_kg,
                e.name AS exercise_name,
                e.slug AS exercise_slug,
                COALESCE(eq.name, 'Brak sprzętu') AS equipment,
                COALESCE((
                    SELECT STRING_AGG(mg.name, ', ' ORDER BY mg.name)
                    FROM exercise_muscle_groups emg
                    JOIN muscle_groups mg ON mg.id = emg.muscle_group_id
                    WHERE emg.exercise_id = e.id
                      AND emg.involvement = 'primary'
                ), '') AS muscle_groups
            FROM performed_sets ps
            JOIN workout_sessions ws ON ws.id = ps.workout_session_id
            JOIN exercises e ON e.id = ps.exercise_id
            LEFT JOIN equipment eq ON eq.id = e.equipment_id
            WHERE ws.user_id = :user_id
              AND ws.id = :session_id
            ORDER BY ps.performed_at DESC, ps.id DESC;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    private function findActiveSessionRow(PDO $connection, int $userId, bool $forUpdate = false): ?array
    {
        $query = $connection->prepare(
            "
            SELECT id
            FROM workout_sessions
            WHERE user_id = :user_id
              AND status = 'in_progress'
            ORDER BY started_at DESC
            LIMIT 1
            ".($forUpdate ? 'FOR UPDATE' : '').";
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        $session = $query->fetch();
        return $session ?: null;
    }

    private function createSession(PDO $connection, int $userId): int
    {
        $planQuery = $connection->prepare(
            "
            SELECT
                uwp.workout_plan_id,
                COALESCE(uwp.custom_name, wp.name) AS plan_name
            FROM user_workout_plans uwp
            JOIN workout_plans wp ON wp.id = uwp.workout_plan_id
            WHERE uwp.user_id = :user_id
              AND uwp.is_active = TRUE
            ORDER BY uwp.assigned_at DESC
            LIMIT 1;
            "
        );
        $planQuery->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $planQuery->execute();
        $plan = $planQuery->fetch();

        $workoutPlanId = $plan ? (int) $plan['workout_plan_id'] : null;
        $workoutPlanDayId = null;
        $dayName = null;

        if ($workoutPlanId !== null) {
            $dayQuery = $connection->prepare(
                "
                SELECT id, name
                FROM workout_plan_days
                WHERE workout_plan_id = :workout_plan_id
                ORDER BY
                    CASE WHEN day_of_week = :weekday THEN 0 ELSE 1 END,
                    day_order ASC
                LIMIT 1;
                "
            );
            $dayQuery->bindValue(':workout_plan_id', $workoutPlanId, PDO::PARAM_INT);
            $dayQuery->bindValue(':weekday', (int) date('N'), PDO::PARAM_INT);
            $dayQuery->execute();
            $day = $dayQuery->fetch();

            if ($day) {
                $workoutPlanDayId = (int) $day['id'];
                $dayName = $day['name'];
            }
        }

        $sessionName = $dayName ?: 'Sesja treningowa';

        $insertQuery = $connection->prepare(
            "
            INSERT INTO workout_sessions (
                user_id,
                workout_plan_id,
                workout_plan_day_id,
                name,
                status,
                started_at
            )
            VALUES (
                :user_id,
                :workout_plan_id,
                :workout_plan_day_id,
                :name,
                'in_progress',
                CURRENT_TIMESTAMP
            )
            RETURNING id;
            "
        );
        $insertQuery->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $insertQuery->bindValue(':workout_plan_id', $workoutPlanId, $workoutPlanId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $insertQuery->bindValue(':workout_plan_day_id', $workoutPlanDayId, $workoutPlanDayId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $insertQuery->bindValue(':name', $sessionName, PDO::PARAM_STR);
        $insertQuery->execute();

        return (int) $insertQuery->fetchColumn();
    }

    private function getActiveExercise(PDO $connection, int $exerciseId): ?array
    {
        $query = $connection->prepare(
            "
            SELECT id, name
            FROM exercises
            WHERE id = :id
              AND is_active = TRUE
            LIMIT 1;
            "
        );
        $query->bindValue(':id', $exerciseId, PDO::PARAM_INT);
        $query->execute();

        $exercise = $query->fetch();
        return $exercise ?: null;
    }

    private function getSessionById(int $userId, int $sessionId): ?array
    {
        return $this->getSessionByIdUsingConnection($this->database->connect(), $userId, $sessionId);
    }

    private function getSessionByIdUsingConnection(PDO $connection, int $userId, int $sessionId): ?array
    {
        $query = $connection->prepare(
            "
            SELECT
                ws.id,
                ws.name,
                ws.status,
                ws.started_at,
                ws.finished_at,
                ws.session_rpe,
                ws.notes,
                wp.name AS plan_name,
                wpd.name AS day_name,
                COUNT(ps.id) AS sets_count,
                COUNT(DISTINCT ps.exercise_id) AS exercises_count,
                calculate_session_volume(ws.id) AS volume_kg
            FROM workout_sessions ws
            LEFT JOIN workout_plans wp ON wp.id = ws.workout_plan_id
            LEFT JOIN workout_plan_days wpd ON wpd.id = ws.workout_plan_day_id
            LEFT JOIN performed_sets ps ON ps.workout_session_id = ws.id
            WHERE ws.user_id = :user_id
              AND ws.id = :session_id
            GROUP BY ws.id, wp.name, wpd.name
            LIMIT 1;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $query->execute();

        $session = $query->fetch();
        return $session ?: null;
    }
}
