<?php

require_once 'AppController.php';
require_once __DIR__.'/../services/DashboardService.php';
require_once __DIR__.'/../services/PlannerService.php';
require_once __DIR__.'/../services/ExerciseService.php';
require_once __DIR__.'/../services/WorkoutService.php';
require_once __DIR__.'/../services/HistoryService.php';

class DashboardController extends AppController
{
    private ?DashboardService $dashboardService = null;
    private ?PlannerService $plannerService = null;
    private ?ExerciseService $exerciseService = null;
    private ?WorkoutService $workoutService = null;
    private ?HistoryService $historyService = null;

    public function index(): void
    {
        $this->requireLogin();
        $this->render('dashboard', $this->dashboardService()->dashboardData((int) $_SESSION['user_id']));
    }

    public function planer(): void
    {
        $this->requireLogin();

        $plannerMessage = $_SESSION['planner_message'] ?? null;
        unset($_SESSION['planner_message']);

        $this->render('planer', $this->plannerService()->plannerData((int) $_SESSION['user_id'], $plannerMessage));
    }

    public function activatePlan(): void
    {
        $this->requirePlannerPost();
        $_SESSION['planner_message'] = $this->plannerService()->activatePlan((int) $_SESSION['user_id'], $_POST['plan_id'] ?? null);
        $this->redirect('/planer');
    }

    public function createPlan(): void
    {
        $this->requirePlannerPost();
        $_SESSION['planner_message'] = $this->plannerService()->createEmptyPlan((int) $_SESSION['user_id'], $_POST);
        $this->redirect('/planer');
    }

    public function editActivePlan(): void
    {
        $this->requirePlannerPost();
        $_SESSION['planner_message'] = $this->plannerService()->makeActivePlanEditable((int) $_SESSION['user_id']);
        $this->redirect('/planer?edit=1');
    }

    public function addPlanExercise(): void
    {
        $this->requirePlannerPost();
        $_SESSION['planner_message'] = $this->plannerService()->addExerciseToActivePlan((int) $_SESSION['user_id'], $_POST);
        $this->redirect('/planer');
    }

    public function updatePlanExercise(): void
    {
        $this->requirePlannerPost();
        $_SESSION['planner_message'] = $this->plannerService()->updatePlanExercise((int) $_SESSION['user_id'], $_POST);
        $this->redirect('/planer');
    }

    public function movePlanExercise(): void
    {
        $this->requirePlannerPost();
        $_SESSION['planner_message'] = $this->plannerService()->movePlanExercise((int) $_SESSION['user_id'], $_POST);
        $this->redirect('/planer');
    }

    public function deletePlanExercise(): void
    {
        $this->requirePlannerPost();
        $_SESSION['planner_message'] = $this->plannerService()->deletePlanExercise((int) $_SESSION['user_id'], $_POST['plan_exercise_id'] ?? null);
        $this->redirect('/planer');
    }

    public function deletePlan(): void
    {
        $this->requirePlannerPost();
        $_SESSION['planner_message'] = $this->plannerService()->deletePlan((int) $_SESSION['user_id'], $_POST['plan_id'] ?? null);
        $this->redirect('/planer');
    }

    public function session(): void
    {
        $this->requireLogin();

        $this->render(
            'session',
            $this->workoutService()->sessionData((int) $_SESSION['user_id'], $this->exerciseService()->activeExercises())
        );
    }

    public function atlas(): void
    {
        $this->requireLogin();
        $this->render('atlas', $this->exerciseService()->atlasData());
    }

    public function history(): void
    {
        $this->requireLogin();
        $this->render('history', $this->historyService()->historyData((int) $_SESSION['user_id']));
    }

    private function requirePlannerPost(): void
    {
        $this->requireLogin();

        if (!$this->isPost()) {
            $this->redirect('/planer');
        }

        $this->requireCsrf();
    }

    private function dashboardService(): DashboardService
    {
        return $this->dashboardService ??= new DashboardService();
    }

    private function plannerService(): PlannerService
    {
        return $this->plannerService ??= new PlannerService();
    }

    private function exerciseService(): ExerciseService
    {
        return $this->exerciseService ??= new ExerciseService();
    }

    private function workoutService(): WorkoutService
    {
        return $this->workoutService ??= new WorkoutService();
    }

    private function historyService(): HistoryService
    {
        return $this->historyService ??= new HistoryService();
    }
}
