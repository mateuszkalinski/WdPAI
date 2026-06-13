<?php

require_once 'AppController.php';
require_once __DIR__.'/../services/AdminService.php';

class AdminController extends AppController
{
    private AdminService $adminService;

    public function __construct()
    {
        $this->adminService = new AdminService();
    }

    public function users(): void
    {
        $this->requireRole('admin');
        $this->render('admin_users', $this->adminService->usersData($_GET['message'] ?? null));
    }

    public function blockUser(): void
    {
        $this->requireRole('admin');
        $this->requirePostCsrf('/admin/users');
        $this->adminService->blockUser($this->postedId(), (int) $_SESSION['user_id']);
        $this->redirect('/admin/users');
    }

    public function unblockUser(): void
    {
        $this->requireRole('admin');
        $this->requirePostCsrf('/admin/users');
        $this->adminService->unblockUser($this->postedId(), (int) $_SESSION['user_id']);
        $this->redirect('/admin/users');
    }

    public function deleteUser(): void
    {
        $this->requireRole('admin');
        $this->requirePostCsrf('/admin/users');
        $this->adminService->deleteUser($this->postedId(), (int) $_SESSION['user_id']);
        $this->redirect('/admin/users');
    }

    public function exercises(): void
    {
        $this->requireRole('admin');
        $this->render('admin_exercises', $this->adminService->exercisesData($_GET['message'] ?? null));
    }

    public function createExercise(): void
    {
        $this->requireRole('admin');
        $this->requirePostCsrf('/admin/exercises');
        $this->redirectWithMessage('/admin/exercises', $this->adminService->createExercise($_POST));
    }

    public function deactivateExercise(): void
    {
        $this->requireRole('admin');
        $this->requirePostCsrf('/admin/exercises');
        $this->adminService->setExerciseActive($this->postedId(), false);
        $this->redirect('/admin/exercises');
    }

    public function activateExercise(): void
    {
        $this->requireRole('admin');
        $this->requirePostCsrf('/admin/exercises');
        $this->adminService->setExerciseActive($this->postedId(), true);
        $this->redirect('/admin/exercises');
    }

    public function badges(): void
    {
        $this->requireRole('admin');
        $this->render('admin_badges', $this->adminService->badgesData($_GET['message'] ?? null));
    }

    public function createBadge(): void
    {
        $this->requireRole('admin');
        $this->requirePostCsrf('/admin/badges');
        $this->redirectWithMessage('/admin/badges', $this->adminService->createBadge((int) $_SESSION['user_id'], $_POST));
    }

    public function deactivateBadge(): void
    {
        $this->requireRole('admin');
        $this->requirePostCsrf('/admin/badges');
        $this->adminService->setBadgeActive($this->postedId(), false);
        $this->redirect('/admin/badges');
    }

    public function activateBadge(): void
    {
        $this->requireRole('admin');
        $this->requirePostCsrf('/admin/badges');
        $this->adminService->setBadgeActive($this->postedId(), true);
        $this->redirect('/admin/badges');
    }

    private function requirePostCsrf(string $fallbackPath): void
    {
        if (!$this->isPost()) {
            $this->redirect($fallbackPath);
        }

        $this->requireCsrf();
    }

    private function postedId(): ?int
    {
        return $this->adminService->nullableInt($_POST['id'] ?? null);
    }

    private function redirectWithMessage(string $path, string $message): void
    {
        $this->redirect($path.'?message='.rawurlencode($message));
    }
}
