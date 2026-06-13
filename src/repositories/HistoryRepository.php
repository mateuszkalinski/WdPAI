<?php

require_once 'Repository.php';

class HistoryRepository extends Repository
{
    public function getHistorySummary(int $userId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                COUNT(DISTINCT ws.id) FILTER (WHERE ws.status = 'finished') AS finished_sessions,
                COALESCE(SUM(ps.weight_kg * ps.reps) FILTER (WHERE ws.status = 'finished'), 0)::NUMERIC(12,2) AS total_volume_kg,
                COUNT(ps.id) FILTER (WHERE ws.status = 'finished') AS total_sets,
                COUNT(DISTINCT ps.exercise_id) FILTER (WHERE ws.status = 'finished') AS exercises_count,
                ROUND(AVG(ws.session_rpe) FILTER (WHERE ws.status = 'finished'), 2) AS average_session_rpe,
                MAX(ws.started_at) FILTER (WHERE ws.status = 'finished') AS last_workout_at
            FROM workout_sessions ws
            LEFT JOIN performed_sets ps ON ps.workout_session_id = ws.id
            WHERE ws.user_id = :user_id;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        $summary = $query->fetch();

        return $summary ?: [
            'finished_sessions' => 0,
            'total_volume_kg' => 0,
            'total_sets' => 0,
            'exercises_count' => 0,
            'average_session_rpe' => null,
            'last_workout_at' => null
        ];
    }

    public function getSessions(int $userId, int $limit = 12): array
    {
        $query = $this->database->connect()->prepare(
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
                ROUND(AVG(ps.rpe), 2) AS average_set_rpe,
                COALESCE(SUM(ps.weight_kg * ps.reps), 0)::NUMERIC(12,2) AS volume_kg,
                CASE
                    WHEN ws.finished_at IS NULL THEN NULL
                    ELSE ROUND(EXTRACT(EPOCH FROM (ws.finished_at - ws.started_at)) / 60)::INTEGER
                END AS duration_minutes
            FROM workout_sessions ws
            LEFT JOIN workout_plans wp ON wp.id = ws.workout_plan_id
            LEFT JOIN workout_plan_days wpd ON wpd.id = ws.workout_plan_day_id
            LEFT JOIN performed_sets ps ON ps.workout_session_id = ws.id
            WHERE ws.user_id = :user_id
            GROUP BY ws.id, wp.name, wpd.name
            ORDER BY ws.started_at DESC
            LIMIT :limit;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function getSetsForSessions(int $userId, array $sessionIds): array
    {
        $sessionIds = array_values(array_unique(array_map('intval', $sessionIds)));

        if (empty($sessionIds)) {
            return [];
        }

        $placeholders = [];
        foreach ($sessionIds as $index => $sessionId) {
            $placeholders[] = ':session_id_'.$index;
        }

        $query = $this->database->connect()->prepare(
            "
            SELECT
                ps.workout_session_id,
                ps.id,
                ps.set_order,
                ps.set_type,
                ps.weight_kg,
                ps.reps,
                ps.rpe,
                ps.note,
                ps.performed_at,
                e.name AS exercise_name,
                e.slug AS exercise_slug,
                COALESCE(eq.name, 'Brak sprzętu') AS equipment_name,
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
              AND ps.workout_session_id IN (".implode(', ', $placeholders).")
            ORDER BY ws.started_at DESC, ps.performed_at ASC, e.name ASC, ps.set_order ASC;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);

        foreach ($sessionIds as $index => $sessionId) {
            $query->bindValue(':session_id_'.$index, $sessionId, PDO::PARAM_INT);
        }

        $query->execute();

        $setsBySession = [];
        foreach ($query->fetchAll() as $set) {
            $setsBySession[(int) $set['workout_session_id']][] = $set;
        }

        return $setsBySession;
    }

    public function getExerciseRecords(int $userId, int $limit = 5): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                e.name AS exercise_name,
                MAX(ps.weight_kg) AS max_weight_kg,
                MAX(ps.weight_kg * ps.reps) AS max_set_volume_kg,
                MAX(ps.reps) AS max_reps,
                COUNT(ps.id) AS sets_count,
                MAX(ws.started_at) AS last_performed_at
            FROM performed_sets ps
            JOIN workout_sessions ws ON ws.id = ps.workout_session_id
            JOIN exercises e ON e.id = ps.exercise_id
            WHERE ws.user_id = :user_id
              AND ws.status = 'finished'
            GROUP BY e.id, e.name
            ORDER BY max_weight_kg DESC, max_set_volume_kg DESC, sets_count DESC
            LIMIT :limit;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }
}
