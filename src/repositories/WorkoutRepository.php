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
            $this->attachActivePlanToSessionIfMissing($connection, $userId, $sessionId);
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
            $this->attachActivePlanToSessionIfMissing($connection, $userId, $sessionId);

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
            $setId = (int) $insertQuery->fetchColumn();
            $set = $this->getSetByIdUsingConnection($connection, $userId, $setId);

            $session = $this->getSessionByIdUsingConnection($connection, $userId, $sessionId);

            $connection->commit();

            return [
                'session' => $session,
                'set' => $set
            ];
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function skipPlannedItem(int $userId, int $planExerciseId): array
    {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();

            $sessionRow = $this->findActiveSessionRow($connection, $userId, true);
            $sessionId = $sessionRow ? (int) $sessionRow['id'] : $this->createSession($connection, $userId);
            $this->attachActivePlanToSessionIfMissing($connection, $userId, $sessionId);

            $planExercise = $this->getPlanExerciseForSession($connection, $userId, $sessionId, $planExerciseId);
            if (!$planExercise) {
                throw new InvalidArgumentException('Nie znaleziono tego cwiczenia w aktywnym planie.');
            }

            $progress = $this->getPlanExerciseProgress($connection, $sessionId, $planExerciseId, (int) $planExercise['exercise_id']);
            $targetSets = (int) $planExercise['target_sets'];
            $completedSets = (int) $progress['completed_sets'];
            $skippedSets = (int) $progress['skipped_sets'];
            $currentProgress = min($targetSets, $completedSets + $skippedSets);

            if ($currentProgress >= $targetSets) {
                throw new InvalidArgumentException('To cwiczenie jest juz zamkniete w planie.');
            }

            $remainingSets = $targetSets - $currentProgress;
            $newSkippedSets = min($targetSets, $skippedSets + $remainingSets);

            $this->upsertSkippedSets(
                $connection,
                $sessionId,
                $planExerciseId,
                (int) $planExercise['exercise_id'],
                $newSkippedSets,
                'Przejscie do nastepnego cwiczenia'
            );

            $session = $this->getSessionByIdUsingConnection($connection, $userId, $sessionId);

            $connection->commit();

            return [
                'session' => $session,
                'skipped' => [
                    'plan_exercise_id' => $planExerciseId,
                    'exercise_id' => (int) $planExercise['exercise_id'],
                    'skipped_sets' => $newSkippedSets
                ]
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

            $this->awardProgressBadges($connection, $userId, $sessionId);
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

    public function getPlannedWorkoutPreview(int $userId): ?array
    {
        $connection = $this->database->connect();
        $plan = $this->getActivePlanContext($connection, $userId);

        if (!$plan) {
            return null;
        }

        $day = $this->choosePlanDay($connection, $userId, (int) $plan['workout_plan_id'], $plan['assigned_at'] ?? null);

        return [
            'plan_id' => (int) $plan['workout_plan_id'],
            'plan_name' => $plan['plan_name'],
            'day_id' => $day ? (int) $day['id'] : null,
            'day_name' => $day['name'] ?? null,
            'day_order' => $day ? (int) $day['day_order'] : null,
            'exercises' => $day ? $this->getPlannedExercisesForDay($connection, $userId, (int) $day['id']) : []
        ];
    }

    public function getPlannedWorkoutForSession(int $userId, int $sessionId): ?array
    {
        $connection = $this->database->connect();
        $context = $this->getSessionPlanContext($connection, $userId, $sessionId);

        if (!$context || empty($context['workout_plan_id'])) {
            return null;
        }

        $dayId = $context['workout_plan_day_id'] !== null ? (int) $context['workout_plan_day_id'] : null;

        return [
            'plan_id' => (int) $context['workout_plan_id'],
            'plan_name' => $context['plan_name'],
            'day_id' => $dayId,
            'day_name' => $context['day_name'] ?? null,
            'day_order' => $context['day_order'] !== null ? (int) $context['day_order'] : null,
            'exercises' => $dayId ? $this->getPlannedExercisesForDay($connection, $userId, $dayId, $sessionId) : []
        ];
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
        $plan = $this->getActivePlanContext($connection, $userId);

        $workoutPlanId = $plan ? (int) $plan['workout_plan_id'] : null;
        $workoutPlanDayId = null;
        $dayName = null;

        if ($workoutPlanId !== null) {
            $day = $this->choosePlanDay($connection, $userId, $workoutPlanId, $plan['assigned_at'] ?? null);

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

    private function attachActivePlanToSessionIfMissing(PDO $connection, int $userId, int $sessionId): void
    {
        $sessionQuery = $connection->prepare(
            "
            SELECT workout_plan_id
            FROM workout_sessions
            WHERE id = :session_id
              AND user_id = :user_id
              AND status = 'in_progress'
            LIMIT 1
            FOR UPDATE;
            "
        );
        $sessionQuery->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $sessionQuery->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $sessionQuery->execute();
        $session = $sessionQuery->fetch();

        if (!$session || $session['workout_plan_id'] !== null) {
            return;
        }

        $plan = $this->getActivePlanContext($connection, $userId);
        if (!$plan) {
            return;
        }

        $day = $this->choosePlanDay($connection, $userId, (int) $plan['workout_plan_id'], $plan['assigned_at'] ?? null);
        $workoutPlanDayId = $day ? (int) $day['id'] : null;
        $dayName = $day['name'] ?? null;

        $updateQuery = $connection->prepare(
            "
            UPDATE workout_sessions
            SET
                workout_plan_id = :workout_plan_id,
                workout_plan_day_id = :workout_plan_day_id,
                name = CASE
                    WHEN name = 'Sesja treningowa' AND :day_name_for_case IS NOT NULL THEN :day_name_for_value
                    ELSE name
                END
            WHERE id = :session_id
              AND user_id = :user_id;
            "
        );
        $updateQuery->bindValue(':workout_plan_id', (int) $plan['workout_plan_id'], PDO::PARAM_INT);
        $updateQuery->bindValue(':workout_plan_day_id', $workoutPlanDayId, $workoutPlanDayId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $updateQuery->bindValue(':day_name_for_case', $dayName, $dayName === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $updateQuery->bindValue(':day_name_for_value', $dayName, $dayName === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $updateQuery->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $updateQuery->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $updateQuery->execute();
    }

    private function getPlanExerciseForSession(PDO $connection, int $userId, int $sessionId, int $planExerciseId): ?array
    {
        $query = $connection->prepare(
            "
            SELECT
                wpe.id,
                wpe.exercise_id,
                wpe.target_sets
            FROM workout_sessions ws
            JOIN workout_plan_exercises wpe ON wpe.workout_plan_day_id = ws.workout_plan_day_id
            JOIN exercises e ON e.id = wpe.exercise_id
            WHERE ws.id = :session_id
              AND ws.user_id = :user_id
              AND wpe.id = :plan_exercise_id
              AND e.is_active = TRUE
            LIMIT 1;
            "
        );
        $query->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':plan_exercise_id', $planExerciseId, PDO::PARAM_INT);
        $query->execute();

        $planExercise = $query->fetch();
        return $planExercise ?: null;
    }

    private function getPlanExerciseProgress(PDO $connection, int $sessionId, int $planExerciseId, int $exerciseId): array
    {
        $query = $connection->prepare(
            "
            SELECT
                (
                    SELECT COUNT(*)
                    FROM performed_sets
                    WHERE workout_session_id = :session_id_for_sets
                      AND exercise_id = :exercise_id
                ) AS completed_sets,
                COALESCE((
                    SELECT skipped_sets
                    FROM workout_session_plan_skips
                    WHERE workout_session_id = :session_id_for_skips
                      AND workout_plan_exercise_id = :plan_exercise_id
                ), 0) AS skipped_sets;
            "
        );
        $query->bindValue(':session_id_for_sets', $sessionId, PDO::PARAM_INT);
        $query->bindValue(':session_id_for_skips', $sessionId, PDO::PARAM_INT);
        $query->bindValue(':exercise_id', $exerciseId, PDO::PARAM_INT);
        $query->bindValue(':plan_exercise_id', $planExerciseId, PDO::PARAM_INT);
        $query->execute();

        return $query->fetch() ?: ['completed_sets' => 0, 'skipped_sets' => 0];
    }

    private function upsertSkippedSets(
        PDO $connection,
        int $sessionId,
        int $planExerciseId,
        int $exerciseId,
        int $skippedSets,
        string $note
    ): void {
        $query = $connection->prepare(
            "
            INSERT INTO workout_session_plan_skips (
                workout_session_id,
                workout_plan_exercise_id,
                exercise_id,
                skipped_sets,
                note
            )
            VALUES (
                :session_id,
                :plan_exercise_id,
                :exercise_id,
                :skipped_sets,
                :note
            )
            ON CONFLICT (workout_session_id, workout_plan_exercise_id)
            DO UPDATE SET
                skipped_sets = EXCLUDED.skipped_sets,
                note = EXCLUDED.note,
                skipped_at = CURRENT_TIMESTAMP;
            "
        );
        $query->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $query->bindValue(':plan_exercise_id', $planExerciseId, PDO::PARAM_INT);
        $query->bindValue(':exercise_id', $exerciseId, PDO::PARAM_INT);
        $query->bindValue(':skipped_sets', $skippedSets, PDO::PARAM_INT);
        $query->bindValue(':note', $note, PDO::PARAM_STR);
        $query->execute();
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
                COALESCE(SUM(ps.weight_kg * ps.reps), 0)::NUMERIC(12,2) AS volume_kg
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

    private function awardProgressBadges(PDO $connection, int $userId, int $sessionId): void
    {
        $this->awardTotalSessionBadges($connection, $userId, $sessionId);
        $this->awardTotalVolumeBadges($connection, $userId, $sessionId);
        $this->awardMuscleSetBadges($connection, $userId, $sessionId);
    }

    private function awardTotalSessionBadges(PDO $connection, int $userId, int $sessionId): void
    {
        $query = $connection->prepare(
            "
            INSERT INTO user_badges (user_id, badge_id, source_workout_session_id, current_value)
            SELECT
                :badge_user_id,
                b.id,
                :session_id,
                stats.finished_sessions
            FROM badges b
            CROSS JOIN (
                SELECT COUNT(*)::NUMERIC(10,2) AS finished_sessions
                FROM workout_sessions
                WHERE user_id = :stats_user_id
                  AND status = 'finished'
            ) stats
            WHERE b.is_active = TRUE
              AND b.criteria_type = 'total_sessions'
              AND stats.finished_sessions >= b.target_value
            ON CONFLICT (user_id, badge_id) DO UPDATE
            SET current_value = GREATEST(user_badges.current_value, EXCLUDED.current_value),
                source_workout_session_id = EXCLUDED.source_workout_session_id
            WHERE EXCLUDED.current_value > user_badges.current_value;
            "
        );
        $query->bindValue(':badge_user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':stats_user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $query->execute();
    }

    private function awardTotalVolumeBadges(PDO $connection, int $userId, int $sessionId): void
    {
        $query = $connection->prepare(
            "
            INSERT INTO user_badges (user_id, badge_id, source_workout_session_id, current_value)
            SELECT
                :badge_user_id,
                b.id,
                :session_id,
                stats.total_volume_kg
            FROM badges b
            CROSS JOIN (
                SELECT COALESCE(SUM(ps.weight_kg * ps.reps), 0)::NUMERIC(10,2) AS total_volume_kg
                FROM workout_sessions ws
                JOIN performed_sets ps ON ps.workout_session_id = ws.id
                WHERE ws.user_id = :stats_user_id
                  AND ws.status = 'finished'
            ) stats
            WHERE b.is_active = TRUE
              AND b.criteria_type = 'total_volume'
              AND stats.total_volume_kg >= b.target_value
            ON CONFLICT (user_id, badge_id) DO UPDATE
            SET current_value = GREATEST(user_badges.current_value, EXCLUDED.current_value),
                source_workout_session_id = EXCLUDED.source_workout_session_id
            WHERE EXCLUDED.current_value > user_badges.current_value;
            "
        );
        $query->bindValue(':badge_user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':stats_user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $query->execute();
    }

    private function awardMuscleSetBadges(PDO $connection, int $userId, int $sessionId): void
    {
        $query = $connection->prepare(
            "
            INSERT INTO user_badges (user_id, badge_id, source_workout_session_id, current_value)
            SELECT
                :badge_user_id,
                b.id,
                :session_id,
                stats.sets_count
            FROM badges b
            JOIN (
                SELECT
                    emg.muscle_group_id,
                    COUNT(ps.id)::NUMERIC(10,2) AS sets_count
                FROM workout_sessions ws
                JOIN performed_sets ps ON ps.workout_session_id = ws.id
                JOIN exercise_muscle_groups emg ON emg.exercise_id = ps.exercise_id
                    AND emg.involvement = 'primary'
                WHERE ws.user_id = :stats_user_id
                  AND ws.status = 'finished'
                GROUP BY emg.muscle_group_id
            ) stats ON stats.muscle_group_id = b.muscle_group_id
            WHERE b.is_active = TRUE
              AND b.criteria_type = 'muscle_sets'
              AND stats.sets_count >= b.target_value
            ON CONFLICT (user_id, badge_id) DO UPDATE
            SET current_value = GREATEST(user_badges.current_value, EXCLUDED.current_value),
                source_workout_session_id = EXCLUDED.source_workout_session_id
            WHERE EXCLUDED.current_value > user_badges.current_value;
            "
        );
        $query->bindValue(':badge_user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':stats_user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $query->execute();
    }

    private function getSetByIdUsingConnection(PDO $connection, int $userId, int $setId): ?array
    {
        $query = $connection->prepare(
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
                COALESCE(eq.name, 'Brak sprzÄ™tu') AS equipment,
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
              AND ps.id = :set_id
            LIMIT 1;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':set_id', $setId, PDO::PARAM_INT);
        $query->execute();

        $set = $query->fetch();
        return $set ?: null;
    }

    private function getActivePlanContext(PDO $connection, int $userId): ?array
    {
        $query = $connection->prepare(
            "
            SELECT
                uwp.workout_plan_id,
                COALESCE(uwp.custom_name, wp.name) AS plan_name,
                uwp.assigned_at
            FROM user_workout_plans uwp
            JOIN workout_plans wp ON wp.id = uwp.workout_plan_id
            WHERE uwp.user_id = :user_id
              AND uwp.is_active = TRUE
            ORDER BY uwp.assigned_at DESC
            LIMIT 1;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        $plan = $query->fetch();
        return $plan ?: null;
    }

    private function choosePlanDay(PDO $connection, int $userId, int $workoutPlanId, ?string $assignedAt = null): ?array
    {
        $query = $connection->prepare(
            "
            WITH ordered_days AS (
                SELECT id, name, day_order, day_of_week
                FROM workout_plan_days
                WHERE workout_plan_id = :workout_plan_id_for_days
            ),
            last_session_day AS (
                SELECT wpd.day_order
                FROM workout_sessions ws
                JOIN workout_plan_days wpd ON wpd.id = ws.workout_plan_day_id
                WHERE ws.user_id = :user_id
                  AND ws.workout_plan_id = :workout_plan_id_for_sessions
                  AND ws.status = 'finished'
                  AND (
                      CAST(:assigned_at_for_null AS timestamptz) IS NULL
                      OR ws.started_at >= CAST(:assigned_at_for_compare AS timestamptz)
                  )
                ORDER BY ws.finished_at DESC NULLS LAST, ws.started_at DESC, ws.id DESC
                LIMIT 1
            )
            SELECT id, name, day_order, day_of_week
            FROM (
                SELECT od.id, od.name, od.day_order, od.day_of_week, 0 AS priority
                FROM ordered_days od
                CROSS JOIN (SELECT day_order FROM last_session_day) lsd
                WHERE od.day_order > lsd.day_order
                UNION ALL
                SELECT od.id, od.name, od.day_order, od.day_of_week, 1 AS priority
                FROM ordered_days od
            ) candidates
            ORDER BY priority ASC, day_order ASC
            LIMIT 1;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':workout_plan_id_for_days', $workoutPlanId, PDO::PARAM_INT);
        $query->bindValue(':workout_plan_id_for_sessions', $workoutPlanId, PDO::PARAM_INT);
        $query->bindValue(':assigned_at_for_null', $assignedAt, $assignedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $query->bindValue(':assigned_at_for_compare', $assignedAt, $assignedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $query->execute();

        $day = $query->fetch();
        return $day ?: null;
    }

    private function getSessionPlanContext(PDO $connection, int $userId, int $sessionId): ?array
    {
        $query = $connection->prepare(
            "
            SELECT
                ws.workout_plan_id,
                ws.workout_plan_day_id,
                COALESCE(uwp.custom_name, wp.name) AS plan_name,
                wpd.name AS day_name,
                wpd.day_order
            FROM workout_sessions ws
            LEFT JOIN workout_plans wp ON wp.id = ws.workout_plan_id
            LEFT JOIN user_workout_plans uwp ON uwp.user_id = ws.user_id
                AND uwp.workout_plan_id = ws.workout_plan_id
            LEFT JOIN workout_plan_days wpd ON wpd.id = ws.workout_plan_day_id
            WHERE ws.user_id = :user_id
              AND ws.id = :session_id
            LIMIT 1;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $query->execute();

        $context = $query->fetch();
        return $context ?: null;
    }

    private function getPlannedExercisesForDay(PDO $connection, int $userId, int $dayId, ?int $sessionId = null): array
    {
        $query = $connection->prepare(
            "
            SELECT
                wpe.id AS plan_exercise_id,
                wpe.exercise_id,
                wpe.exercise_order,
                wpe.target_sets,
                wpe.target_reps_min,
                wpe.target_reps_max,
                wpe.target_rpe,
                wpe.rest_seconds,
                wpe.notes,
                e.name AS exercise_name,
                e.slug AS exercise_slug,
                COALESCE(eq.name, 'Brak sprzetu') AS equipment,
                COALESCE((
                    SELECT STRING_AGG(mg.name, ', ' ORDER BY mg.name)
                    FROM exercise_muscle_groups emg
                    JOIN muscle_groups mg ON mg.id = emg.muscle_group_id
                    WHERE emg.exercise_id = e.id
                ), '') AS muscle_groups,
                COALESCE((
                    SELECT mg.name
                    FROM exercise_muscle_groups emg
                    JOIN muscle_groups mg ON mg.id = emg.muscle_group_id
                    WHERE emg.exercise_id = e.id
                      AND emg.involvement = 'primary'
                    ORDER BY mg.name
                    LIMIT 1
                ), 'Ogolne') AS primary_muscle_group,
                COUNT(ps.id) AS completed_sets,
                COALESCE(wsps.skipped_sets, 0) AS skipped_sets,
                LEAST(wpe.target_sets, COUNT(ps.id) + COALESCE(wsps.skipped_sets, 0)) AS progress_sets,
                last_set.weight_kg AS last_weight_kg,
                last_set.reps AS last_reps,
                last_set.rpe AS last_rpe,
                last_set.performed_at AS last_performed_at
            FROM workout_plan_exercises wpe
            JOIN exercises e ON e.id = wpe.exercise_id
            LEFT JOIN equipment eq ON eq.id = e.equipment_id
            LEFT JOIN performed_sets ps ON ps.exercise_id = wpe.exercise_id
                AND ps.workout_session_id = :session_id
            LEFT JOIN workout_session_plan_skips wsps ON wsps.workout_plan_exercise_id = wpe.id
                AND wsps.workout_session_id = :session_id_for_skips
            LEFT JOIN LATERAL (
                SELECT
                    previous_set.weight_kg,
                    previous_set.reps,
                    previous_set.rpe,
                    previous_set.performed_at
                FROM performed_sets previous_set
                JOIN workout_sessions previous_session ON previous_session.id = previous_set.workout_session_id
                WHERE previous_session.user_id = :user_id
                  AND previous_set.exercise_id = wpe.exercise_id
                  AND previous_session.status IN ('in_progress', 'finished')
                ORDER BY previous_set.performed_at DESC, previous_set.id DESC
                LIMIT 1
            ) last_set ON TRUE
            WHERE wpe.workout_plan_day_id = :day_id
              AND e.is_active = TRUE
            GROUP BY
                wpe.id,
                e.id,
                eq.name,
                wsps.skipped_sets,
                last_set.weight_kg,
                last_set.reps,
                last_set.rpe,
                last_set.performed_at
            ORDER BY wpe.exercise_order ASC;
            "
        );
        $query->bindValue(':day_id', $dayId, PDO::PARAM_INT);
        $query->bindValue(':session_id', $sessionId, $sessionId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $query->bindValue(':session_id_for_skips', $sessionId, $sessionId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }
}
