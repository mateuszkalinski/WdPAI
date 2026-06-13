<?php

require_once __DIR__.'/../repositories/DashboardRepository.php';

class DashboardService
{
    private DashboardRepository $dashboardRepository;

    public function __construct(?DashboardRepository $dashboardRepository = null)
    {
        $this->dashboardRepository = $dashboardRepository ?? new DashboardRepository();
    }

    public function dashboardData(int $userId): array
    {
        return [
            'title' => 'Dashboard',
            'summary' => $this->dashboardRepository->getTrainingSummary($userId),
            'weeklyMuscles' => $this->dashboardRepository->getWeeklyMuscleSummary($userId),
            'lastSession' => $this->dashboardRepository->getLastSession($userId),
            'recentSessions' => $this->dashboardRepository->getRecentSessions($userId),
            'badges' => $this->dashboardRepository->getBadges($userId),
            'activePlan' => $this->dashboardRepository->getActivePlan($userId),
            'activeTab' => 'dashboard'
        ];
    }
}
