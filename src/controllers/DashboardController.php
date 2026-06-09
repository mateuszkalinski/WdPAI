<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/DashboardRepository.php';
require_once __DIR__.'/../repositories/ExerciseRepository.php';
require_once __DIR__.'/../repositories/HistoryRepository.php';
require_once __DIR__.'/../repositories/WorkoutRepository.php';

class DashboardController extends AppController
{
    public function index(): void
    {
        $this->requireLogin();

        $userId = (int) $_SESSION['user_id'];
        $dashboardRepository = new DashboardRepository();

        $this->render("dashboard", [
            "title" => "Dashboard",
            "summary" => $dashboardRepository->getTrainingSummary($userId),
            "weeklyMuscles" => $dashboardRepository->getWeeklyMuscleSummary($userId),
            "lastSession" => $dashboardRepository->getLastSession($userId),
            "recentSessions" => $dashboardRepository->getRecentSessions($userId),
            "badges" => $dashboardRepository->getBadges($userId),
            "activePlan" => $dashboardRepository->getActivePlan($userId),
            "activeTab" => "dashboard"
        ]);
    }

    public function planer(): void
    {
        $this->requireLogin();
        $this->render("planer", ["activeTab" => "planer"]);
    }

    public function session(): void
    {
        $this->requireLogin();

        $userId = (int) $_SESSION['user_id'];
        $exerciseRepository = new ExerciseRepository();
        $workoutRepository = new WorkoutRepository();
        $activeSession = $workoutRepository->getActiveSession($userId);

        $this->render("session", [
            "title" => "Sesja treningowa",
            "activeTab" => "session",
            "exercises" => $exerciseRepository->getActiveExercises(),
            "activeSession" => $activeSession,
            "sets" => $activeSession ? $workoutRepository->getSetsForSession($userId, (int) $activeSession['id']) : []
        ]);
    }

    public function atlas(): void
    {
        $this->requireLogin();

        $exerciseRepository = new ExerciseRepository();

        $this->render("atlas", [
            "activeTab" => "atlas",
            "exercises" => $exerciseRepository->getActiveExercises(),
            "muscleGroups" => $exerciseRepository->getMuscleGroups()
        ]);
    }

    public function history(): void
    {
        $this->requireLogin();

        $userId = (int) $_SESSION['user_id'];
        $historyRepository = new HistoryRepository();
        $sessions = $historyRepository->getSessions($userId);

        $this->render("history", [
            "title" => "Historia",
            "activeTab" => "history",
            "summary" => $historyRepository->getHistorySummary($userId),
            "sessions" => $sessions,
            "setsBySession" => $historyRepository->getSetsForSessions($userId, array_column($sessions, 'id')),
            "records" => $historyRepository->getExerciseRecords($userId)
        ]);
    }
}
