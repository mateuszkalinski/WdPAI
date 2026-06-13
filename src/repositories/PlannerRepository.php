<?php

require_once 'Repository.php';

class PlannerRepository extends Repository
{
    public function getAvailablePlans(int $userId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                wp.id,
                wp.name,
                wp.description,
                wp.goal,
                wp.level,
                wp.is_template,
                wp.is_public,
                wp.owner_user_id,
                uwp.is_active AS user_is_active,
                COUNT(DISTINCT wpd.id) AS days_count,
                COUNT(wpe.id) AS exercises_count
            FROM workout_plans wp
            LEFT JOIN user_workout_plans uwp ON uwp.workout_plan_id = wp.id AND uwp.user_id = :user_id
            LEFT JOIN workout_plan_days wpd ON wpd.workout_plan_id = wp.id
            LEFT JOIN workout_plan_exercises wpe ON wpe.workout_plan_day_id = wpd.id
            WHERE wp.is_public = TRUE
               OR wp.owner_user_id = :owner_user_id
            GROUP BY wp.id, uwp.is_active
            ORDER BY COALESCE(uwp.is_active, FALSE) DESC, wp.is_template DESC, wp.created_at DESC;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':owner_user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function getActivePlan(int $userId): ?array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                wp.id,
                COALESCE(uwp.custom_name, wp.name) AS display_name,
                wp.name,
                wp.description,
                wp.goal,
                wp.level,
                wp.is_template,
                wp.is_public,
                wp.owner_user_id,
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

    public function getPlanDays(int $planId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT id, name, day_of_week, day_order, notes
            FROM workout_plan_days
            WHERE workout_plan_id = :plan_id
            ORDER BY day_order ASC;
            "
        );
        $query->bindValue(':plan_id', $planId, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function getPlanExercises(int $planId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                wpe.id,
                wpd.id AS day_id,
                wpd.day_order,
                wpe.exercise_order,
                wpe.target_sets,
                wpe.target_reps_min,
                wpe.target_reps_max,
                wpe.target_rpe,
                wpe.rest_seconds,
                wpe.notes,
                e.id AS exercise_id,
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
            FROM workout_plan_exercises wpe
            JOIN workout_plan_days wpd ON wpd.id = wpe.workout_plan_day_id
            JOIN exercises e ON e.id = wpe.exercise_id
            LEFT JOIN equipment eq ON eq.id = e.equipment_id
            WHERE wpd.workout_plan_id = :plan_id
            ORDER BY wpd.day_order ASC, wpe.exercise_order ASC;
            "
        );
        $query->bindValue(':plan_id', $planId, PDO::PARAM_INT);
        $query->execute();

        $grouped = [];
        foreach ($query->fetchAll() as $exercise) {
            $grouped[(int) $exercise['day_order']][] = $exercise;
        }

        return $grouped;
    }

    public function getPlanVolumeByMuscle(int $planId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                mg.name AS muscle_group,
                SUM(wpe.target_sets) AS sets_count
            FROM workout_plan_exercises wpe
            JOIN workout_plan_days wpd ON wpd.id = wpe.workout_plan_day_id
            JOIN exercise_muscle_groups emg ON emg.exercise_id = wpe.exercise_id AND emg.involvement = 'primary'
            JOIN muscle_groups mg ON mg.id = emg.muscle_group_id
            WHERE wpd.workout_plan_id = :plan_id
            GROUP BY mg.id, mg.name
            ORDER BY sets_count DESC, mg.name ASC
            LIMIT 6;
            "
        );
        $query->bindValue(':plan_id', $planId, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function activatePlan(int $userId, int $planId): bool
    {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();

            if (!$this->canUsePlan($connection, $userId, $planId)) {
                $connection->rollBack();
                return false;
            }

            $this->deactivateUserPlans($connection, $userId);

            $query = $connection->prepare(
                "
                INSERT INTO user_workout_plans (user_id, workout_plan_id, custom_name, is_active)
                VALUES (:user_id, :plan_id, NULL, TRUE)
                ON CONFLICT (user_id, workout_plan_id) DO UPDATE
                SET is_active = TRUE,
                    assigned_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP;
                "
            );
            $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $query->bindValue(':plan_id', $planId, PDO::PARAM_INT);
            $query->execute();

            $connection->commit();
            return true;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function addExerciseToActivePlan(
        int $userId,
        int $dayOrder,
        int $exerciseId,
        int $targetSets,
        int $targetRepsMin,
        int $targetRepsMax
    ): int {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();

            $editablePlanId = $this->ensureEditableActivePlan($connection, $userId);
            $dayId = $this->getDayIdByOrder($connection, $editablePlanId, $dayOrder);

            if ($dayId === null || !$this->activeExerciseExists($connection, $exerciseId)) {
                throw new InvalidArgumentException('Nieprawidłowy dzień albo ćwiczenie.');
            }

            $orderQuery = $connection->prepare(
                "
                SELECT COALESCE(MAX(exercise_order), 0) + 1
                FROM workout_plan_exercises
                WHERE workout_plan_day_id = :day_id;
                "
            );
            $orderQuery->bindValue(':day_id', $dayId, PDO::PARAM_INT);
            $orderQuery->execute();
            $exerciseOrder = (int) $orderQuery->fetchColumn();

            $insertQuery = $connection->prepare(
                "
                INSERT INTO workout_plan_exercises (
                    workout_plan_day_id,
                    exercise_id,
                    exercise_order,
                    target_sets,
                    target_reps_min,
                    target_reps_max,
                    target_rpe
                )
                VALUES (
                    :day_id,
                    :exercise_id,
                    :exercise_order,
                    :target_sets,
                    :target_reps_min,
                    :target_reps_max,
                    NULL
                )
                RETURNING id;
                "
            );
            $insertQuery->bindValue(':day_id', $dayId, PDO::PARAM_INT);
            $insertQuery->bindValue(':exercise_id', $exerciseId, PDO::PARAM_INT);
            $insertQuery->bindValue(':exercise_order', $exerciseOrder, PDO::PARAM_INT);
            $insertQuery->bindValue(':target_sets', $targetSets, PDO::PARAM_INT);
            $insertQuery->bindValue(':target_reps_min', $targetRepsMin, PDO::PARAM_INT);
            $insertQuery->bindValue(':target_reps_max', $targetRepsMax, PDO::PARAM_INT);
            $insertQuery->execute();
            $planExerciseId = (int) $insertQuery->fetchColumn();

            $connection->commit();
            return $planExerciseId;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function createEmptyPlan(int $userId, string $name, array $dayNames): int
    {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();

            $planQuery = $connection->prepare(
                "
                INSERT INTO workout_plans (owner_user_id, name, description, goal, level, is_template, is_public)
                VALUES (:user_id, :name, 'Plan utworzony od zera.', 'general', 'beginner', FALSE, FALSE)
                RETURNING id;
                "
            );
            $planQuery->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $planQuery->bindValue(':name', $name, PDO::PARAM_STR);
            $planQuery->execute();
            $planId = (int) $planQuery->fetchColumn();

            $dayQuery = $connection->prepare(
                "
                INSERT INTO workout_plan_days (workout_plan_id, name, day_order)
                VALUES (:plan_id, :name, :day_order);
                "
            );

            foreach (array_values($dayNames) as $index => $dayName) {
                $dayQuery->bindValue(':plan_id', $planId, PDO::PARAM_INT);
                $dayQuery->bindValue(':name', $dayName, PDO::PARAM_STR);
                $dayQuery->bindValue(':day_order', $index + 1, PDO::PARAM_INT);
                $dayQuery->execute();
            }

            $this->deactivateUserPlans($connection, $userId);

            $assignQuery = $connection->prepare(
                "
                INSERT INTO user_workout_plans (user_id, workout_plan_id, custom_name, is_active)
                VALUES (:user_id, :plan_id, NULL, TRUE);
                "
            );
            $assignQuery->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $assignQuery->bindValue(':plan_id', $planId, PDO::PARAM_INT);
            $assignQuery->execute();

            $connection->commit();
            return $planId;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function makeActivePlanEditable(int $userId): int
    {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();
            $planId = $this->ensureEditableActivePlan($connection, $userId);
            $connection->commit();

            return $planId;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function updatePlanExercise(
        int $userId,
        int $planExerciseId,
        int $targetSets,
        int $targetRepsMin,
        int $targetRepsMax
    ): bool {
        $query = $this->database->connect()->prepare(
            "
            UPDATE workout_plan_exercises wpe
            SET target_sets = :target_sets,
                target_reps_min = :target_reps_min,
                target_reps_max = :target_reps_max,
                target_rpe = NULL
            FROM workout_plan_days wpd, workout_plans wp, user_workout_plans uwp
            WHERE wpe.id = :plan_exercise_id
              AND wpd.id = wpe.workout_plan_day_id
              AND wp.id = wpd.workout_plan_id
              AND uwp.workout_plan_id = wp.id
              AND uwp.user_id = :user_id
              AND uwp.is_active = TRUE
              AND wp.owner_user_id = :owner_user_id
              AND wp.is_template = FALSE;
            "
        );
        $query->bindValue(':target_sets', $targetSets, PDO::PARAM_INT);
        $query->bindValue(':target_reps_min', $targetRepsMin, PDO::PARAM_INT);
        $query->bindValue(':target_reps_max', $targetRepsMax, PDO::PARAM_INT);
        $query->bindValue(':plan_exercise_id', $planExerciseId, PDO::PARAM_INT);
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':owner_user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        return $query->rowCount() > 0;
    }

    public function updatePlanExercises(int $userId, array $exercises): bool
    {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();

            $query = $connection->prepare(
                "
                UPDATE workout_plan_exercises wpe
                SET target_sets = :target_sets,
                    target_reps_min = :target_reps_min,
                    target_reps_max = :target_reps_max,
                    target_rpe = NULL
                FROM workout_plan_days wpd, workout_plans wp, user_workout_plans uwp
                WHERE wpe.id = :plan_exercise_id
                  AND wpd.id = wpe.workout_plan_day_id
                  AND wp.id = wpd.workout_plan_id
                  AND uwp.workout_plan_id = wp.id
                  AND uwp.user_id = :user_id
                  AND uwp.is_active = TRUE
                  AND wp.owner_user_id = :owner_user_id
                  AND wp.is_template = FALSE;
                "
            );

            foreach ($exercises as $exercise) {
                $query->bindValue(':target_sets', (int) $exercise['target_sets'], PDO::PARAM_INT);
                $query->bindValue(':target_reps_min', (int) $exercise['target_reps_min'], PDO::PARAM_INT);
                $query->bindValue(':target_reps_max', (int) $exercise['target_reps_max'], PDO::PARAM_INT);
                $query->bindValue(':plan_exercise_id', (int) $exercise['id'], PDO::PARAM_INT);
                $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $query->bindValue(':owner_user_id', $userId, PDO::PARAM_INT);
                $query->execute();
            }

            $connection->commit();
            return true;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function deletePlan(int $userId, int $planId): bool
    {
        $query = $this->database->connect()->prepare(
            "
            DELETE FROM workout_plans
            WHERE id = :plan_id
              AND owner_user_id = :user_id
              AND is_template = FALSE
              AND is_public = FALSE;
            "
        );
        $query->bindValue(':plan_id', $planId, PDO::PARAM_INT);
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        return $query->rowCount() > 0;
    }

    public function movePlanExercise(int $userId, int $planExerciseId, string $direction): bool
    {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();

            $current = $this->getEditablePlanExercise($connection, $userId, $planExerciseId);
            if (!$current) {
                $connection->rollBack();
                return false;
            }

            $operator = $direction === 'up' ? '<' : '>';
            $sort = $direction === 'up' ? 'DESC' : 'ASC';
            $neighbourQuery = $connection->prepare(
                "
                SELECT id, exercise_order
                FROM workout_plan_exercises
                WHERE workout_plan_day_id = :day_id
                  AND exercise_order {$operator} :exercise_order
                ORDER BY exercise_order {$sort}
                LIMIT 1;
                "
            );
            $neighbourQuery->bindValue(':day_id', (int) $current['workout_plan_day_id'], PDO::PARAM_INT);
            $neighbourQuery->bindValue(':exercise_order', (int) $current['exercise_order'], PDO::PARAM_INT);
            $neighbourQuery->execute();
            $neighbour = $neighbourQuery->fetch();

            if (!$neighbour) {
                $connection->rollBack();
                return false;
            }

            $tempOrder = $this->temporaryExerciseOrder($connection, (int) $current['workout_plan_day_id']);
            $updateQuery = $connection->prepare(
                "
                UPDATE workout_plan_exercises
                SET exercise_order = :exercise_order
                WHERE id = :id;
                "
            );

            $updateQuery->execute([
                ':exercise_order' => $tempOrder,
                ':id' => (int) $current['id']
            ]);
            $updateQuery->execute([
                ':exercise_order' => (int) $current['exercise_order'],
                ':id' => (int) $neighbour['id']
            ]);
            $updateQuery->execute([
                ':exercise_order' => (int) $neighbour['exercise_order'],
                ':id' => (int) $current['id']
            ]);

            $connection->commit();
            return true;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function deletePlanExercise(int $userId, int $planExerciseId): bool
    {
        $connection = $this->database->connect();

        try {
            $connection->beginTransaction();
            $current = $this->getEditablePlanExercise($connection, $userId, $planExerciseId);

            if (!$current) {
                $connection->rollBack();
                return false;
            }

            $query = $connection->prepare("DELETE FROM workout_plan_exercises WHERE id = :id;");
            $query->bindValue(':id', $planExerciseId, PDO::PARAM_INT);
            $query->execute();
            $this->normalizeExerciseOrder($connection, (int) $current['workout_plan_day_id']);

            $connection->commit();
            return true;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    private function ensureEditableActivePlan(PDO $connection, int $userId): int
    {
        $activePlan = $this->getActivePlanRowForUpdate($connection, $userId);

        if (!$activePlan) {
            $firstPlanQuery = $connection->prepare(
                "
                SELECT id, name
                FROM workout_plans
                WHERE is_public = TRUE
                ORDER BY is_template DESC, created_at ASC
                LIMIT 1;
                "
            );
            $firstPlanQuery->execute();
            $firstPlan = $firstPlanQuery->fetch();

            if (!$firstPlan) {
                throw new RuntimeException('Brak planu bazowego do skopiowania.');
            }

            $this->clonePlanForUser($connection, $userId, (int) $firstPlan['id'], (string) $firstPlan['name']);
            $activePlan = $this->getActivePlanRowForUpdate($connection, $userId);
        }

        if ((int) $activePlan['owner_user_id'] === $userId && !$this->isTruthy($activePlan['is_template'])) {
            return (int) $activePlan['id'];
        }

        return $this->clonePlanForUser($connection, $userId, (int) $activePlan['id'], (string) $activePlan['name']);
    }

    private function clonePlanForUser(PDO $connection, int $userId, int $sourcePlanId, string $sourcePlanName): int
    {
        $planQuery = $connection->prepare(
            "
            INSERT INTO workout_plans (owner_user_id, name, description, goal, level, is_template, is_public)
            SELECT
                :user_id,
                :name,
                description,
                goal,
                level,
                FALSE,
                FALSE
            FROM workout_plans
            WHERE id = :source_plan_id
            RETURNING id;
            "
        );
        $planQuery->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $planQuery->bindValue(':name', 'Mój '.$sourcePlanName.' '.date('d.m'), PDO::PARAM_STR);
        $planQuery->bindValue(':source_plan_id', $sourcePlanId, PDO::PARAM_INT);
        $planQuery->execute();
        $newPlanId = (int) $planQuery->fetchColumn();

        $daysQuery = $connection->prepare(
            "
            INSERT INTO workout_plan_days (workout_plan_id, name, day_of_week, day_order, notes)
            SELECT :new_plan_id, name, day_of_week, day_order, notes
            FROM workout_plan_days
            WHERE workout_plan_id = :source_plan_id
            ORDER BY day_order;
            "
        );
        $daysQuery->bindValue(':new_plan_id', $newPlanId, PDO::PARAM_INT);
        $daysQuery->bindValue(':source_plan_id', $sourcePlanId, PDO::PARAM_INT);
        $daysQuery->execute();

        $exercisesQuery = $connection->prepare(
            "
            INSERT INTO workout_plan_exercises (
                workout_plan_day_id,
                exercise_id,
                exercise_order,
                target_sets,
                target_reps_min,
                target_reps_max,
                target_rpe,
                rest_seconds,
                notes
            )
            SELECT
                new_day.id,
                wpe.exercise_id,
                wpe.exercise_order,
                wpe.target_sets,
                wpe.target_reps_min,
                wpe.target_reps_max,
                wpe.target_rpe,
                wpe.rest_seconds,
                wpe.notes
            FROM workout_plan_exercises wpe
            JOIN workout_plan_days old_day ON old_day.id = wpe.workout_plan_day_id
            JOIN workout_plan_days new_day ON new_day.workout_plan_id = :new_plan_id AND new_day.day_order = old_day.day_order
            WHERE old_day.workout_plan_id = :source_plan_id;
            "
        );
        $exercisesQuery->bindValue(':new_plan_id', $newPlanId, PDO::PARAM_INT);
        $exercisesQuery->bindValue(':source_plan_id', $sourcePlanId, PDO::PARAM_INT);
        $exercisesQuery->execute();

        $this->deactivateUserPlans($connection, $userId);

        $assignQuery = $connection->prepare(
            "
            INSERT INTO user_workout_plans (user_id, workout_plan_id, custom_name, is_active)
            VALUES (:user_id, :plan_id, :custom_name, TRUE);
            "
        );
        $assignQuery->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $assignQuery->bindValue(':plan_id', $newPlanId, PDO::PARAM_INT);
        $assignQuery->bindValue(':custom_name', 'Mój '.$sourcePlanName, PDO::PARAM_STR);
        $assignQuery->execute();

        return $newPlanId;
    }

    private function canUsePlan(PDO $connection, int $userId, int $planId): bool
    {
        $query = $connection->prepare(
            "
            SELECT 1
            FROM workout_plans
            WHERE id = :plan_id
              AND (is_public = TRUE OR owner_user_id = :user_id)
            LIMIT 1;
            "
        );
        $query->bindValue(':plan_id', $planId, PDO::PARAM_INT);
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        return (bool) $query->fetchColumn();
    }

    private function deactivateUserPlans(PDO $connection, int $userId): void
    {
        $query = $connection->prepare(
            "
            UPDATE user_workout_plans
            SET is_active = FALSE,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = :user_id;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
    }

    private function getActivePlanRowForUpdate(PDO $connection, int $userId): ?array
    {
        $query = $connection->prepare(
            "
            SELECT wp.*
            FROM user_workout_plans uwp
            JOIN workout_plans wp ON wp.id = uwp.workout_plan_id
            WHERE uwp.user_id = :user_id
              AND uwp.is_active = TRUE
            ORDER BY uwp.assigned_at DESC
            LIMIT 1
            FOR UPDATE;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        $plan = $query->fetch();
        return $plan ?: null;
    }

    private function getDayIdByOrder(PDO $connection, int $planId, int $dayOrder): ?int
    {
        $query = $connection->prepare(
            "
            SELECT id
            FROM workout_plan_days
            WHERE workout_plan_id = :plan_id
              AND day_order = :day_order
            LIMIT 1;
            "
        );
        $query->bindValue(':plan_id', $planId, PDO::PARAM_INT);
        $query->bindValue(':day_order', $dayOrder, PDO::PARAM_INT);
        $query->execute();

        $dayId = $query->fetchColumn();
        return $dayId ? (int) $dayId : null;
    }

    private function activeExerciseExists(PDO $connection, int $exerciseId): bool
    {
        $query = $connection->prepare(
            "
            SELECT 1
            FROM exercises
            WHERE id = :exercise_id
              AND is_active = TRUE
            LIMIT 1;
            "
        );
        $query->bindValue(':exercise_id', $exerciseId, PDO::PARAM_INT);
        $query->execute();

        return (bool) $query->fetchColumn();
    }

    private function getEditablePlanExercise(PDO $connection, int $userId, int $planExerciseId): ?array
    {
        $query = $connection->prepare(
            "
            SELECT wpe.id, wpe.workout_plan_day_id, wpe.exercise_order
            FROM workout_plan_exercises wpe
            JOIN workout_plan_days wpd ON wpd.id = wpe.workout_plan_day_id
            JOIN workout_plans wp ON wp.id = wpd.workout_plan_id
            JOIN user_workout_plans uwp ON uwp.workout_plan_id = wp.id
            WHERE wpe.id = :plan_exercise_id
              AND uwp.user_id = :user_id
              AND uwp.is_active = TRUE
              AND wp.owner_user_id = :owner_user_id
              AND wp.is_template = FALSE
            LIMIT 1
            FOR UPDATE;
            "
        );
        $query->bindValue(':plan_exercise_id', $planExerciseId, PDO::PARAM_INT);
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':owner_user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        $exercise = $query->fetch();
        return $exercise ?: null;
    }

    private function temporaryExerciseOrder(PDO $connection, int $dayId): int
    {
        $query = $connection->prepare(
            "
            SELECT COALESCE(MAX(exercise_order), 0) + 1000
            FROM workout_plan_exercises
            WHERE workout_plan_day_id = :day_id;
            "
        );
        $query->bindValue(':day_id', $dayId, PDO::PARAM_INT);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    private function normalizeExerciseOrder(PDO $connection, int $dayId): void
    {
        $offsetQuery = $connection->prepare(
            "
            UPDATE workout_plan_exercises
            SET exercise_order = exercise_order + 1000
            WHERE workout_plan_day_id = :day_id;
            "
        );
        $offsetQuery->bindValue(':day_id', $dayId, PDO::PARAM_INT);
        $offsetQuery->execute();

        $query = $connection->prepare(
            "
            WITH ordered AS (
                SELECT id, ROW_NUMBER() OVER (ORDER BY exercise_order, id) AS new_order
                FROM workout_plan_exercises
                WHERE workout_plan_day_id = :day_id
            )
            UPDATE workout_plan_exercises wpe
            SET exercise_order = ordered.new_order
            FROM ordered
            WHERE wpe.id = ordered.id;
            "
        );
        $query->bindValue(':day_id', $dayId, PDO::PARAM_INT);
        $query->execute();
    }

    private function isTruthy($value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }
}
