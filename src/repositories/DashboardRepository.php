<?php

require_once 'Repository.php';

class DashboardRepository extends Repository
{
    public function getTrainingSummary(int $userId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                user_id,
                username,
                finished_sessions,
                total_volume_kg,
                total_sets,
                average_set_rpe,
                last_workout_at
            FROM user_training_summary
            WHERE user_id = :user_id
            LIMIT 1;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        $summary = $query->fetch();

        return $summary ?: [
            'user_id' => $userId,
            'username' => '',
            'finished_sessions' => 0,
            'total_volume_kg' => 0,
            'total_sets' => 0,
            'average_set_rpe' => null,
            'last_workout_at' => null
        ];
    }

    public function getWeeklyMuscleSummary(int $userId): array
    {
        $query = $this->database->connect()->prepare(
            "
            WITH latest_week AS (
                SELECT MAX(week_start) AS week_start
                FROM weekly_muscle_group_summary
                WHERE user_id = :latest_user_id
            )
            SELECT
                w.muscle_group,
                w.sets_count,
                w.volume_kg,
                w.average_rpe,
                w.week_start
            FROM weekly_muscle_group_summary w
            JOIN latest_week lw ON lw.week_start = w.week_start
            WHERE w.user_id = :user_id
            ORDER BY w.sets_count DESC, w.volume_kg DESC
            LIMIT 4;
            "
        );
        $query->bindValue(':latest_user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function getLastSession(int $userId): ?array
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
                calculate_session_volume(ws.id) AS volume_kg
            FROM workout_sessions ws
            LEFT JOIN workout_plans wp ON wp.id = ws.workout_plan_id
            LEFT JOIN workout_plan_days wpd ON wpd.id = ws.workout_plan_day_id
            LEFT JOIN performed_sets ps ON ps.workout_session_id = ws.id
            WHERE ws.user_id = :user_id
            GROUP BY ws.id, wp.name, wpd.name
            ORDER BY ws.started_at DESC
            LIMIT 1;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        $session = $query->fetch();
        return $session ?: null;
    }

    public function getRecentSessions(int $userId, int $limit = 3): array
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
                COUNT(ps.id) AS sets_count,
                calculate_session_volume(ws.id) AS volume_kg
            FROM workout_sessions ws
            LEFT JOIN performed_sets ps ON ps.workout_session_id = ws.id
            WHERE ws.user_id = :user_id
            GROUP BY ws.id
            ORDER BY ws.started_at DESC
            LIMIT :limit;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function getBadges(int $userId, int $limit = 4): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                badge_name,
                criteria_type,
                target_value,
                current_value,
                awarded_at,
                source_session
            FROM user_badge_overview
            WHERE user_id = :user_id
            ORDER BY awarded_at DESC
            LIMIT :limit;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }

    public function getActivePlan(int $userId): ?array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT
                uwp.custom_name,
                wp.name,
                wp.goal,
                wp.level,
                COUNT(wpd.id) AS training_days
            FROM user_workout_plans uwp
            JOIN workout_plans wp ON wp.id = uwp.workout_plan_id
            LEFT JOIN workout_plan_days wpd ON wpd.workout_plan_id = wp.id
            WHERE uwp.user_id = :user_id
              AND uwp.is_active = TRUE
            GROUP BY uwp.id, wp.id
            ORDER BY uwp.assigned_at DESC
            LIMIT 1;
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        $plan = $query->fetch();
        return $plan ?: null;
    }
}
