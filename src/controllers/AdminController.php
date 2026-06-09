<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/AdminRepository.php';
require_once __DIR__.'/../repositories/ExerciseRepository.php';
require_once __DIR__.'/../repositories/BadgeRepository.php';

class AdminController extends AppController
{
    private AdminRepository $adminRepository;
    private ExerciseRepository $exerciseRepository;
    private BadgeRepository $badgeRepository;

    public function __construct()
    {
        $this->adminRepository = new AdminRepository();
        $this->exerciseRepository = new ExerciseRepository();
        $this->badgeRepository = new BadgeRepository();
    }

    public function users(): void
    {
        $this->requireRole('admin');

        $this->render('admin_users', [
            'activeTab' => 'admin',
            'users' => $this->adminRepository->getUsers(),
            'messages' => $_GET['message'] ?? null
        ]);
    }

    public function blockUser(): void
    {
        $this->requireRole('admin');
        $userId = $this->postedId();

        if ($userId !== null && $this->canModifyUser($userId)) {
            $this->adminRepository->blockUser($userId);
        }

        $this->redirect('/admin/users');
    }

    public function unblockUser(): void
    {
        $this->requireRole('admin');
        $userId = $this->postedId();

        if ($userId !== null && $this->canModifyUser($userId)) {
            $this->adminRepository->unblockUser($userId);
        }

        $this->redirect('/admin/users');
    }

    public function deleteUser(): void
    {
        $this->requireRole('admin');
        $userId = $this->postedId();

        if ($userId !== null && $this->canModifyUser($userId)) {
            $this->adminRepository->deleteUser($userId);
        }

        $this->redirect('/admin/users');
    }

    public function exercises(): void
    {
        $this->requireRole('admin');

        $this->render('admin_exercises', [
            'activeTab' => 'admin',
            'exercises' => $this->exerciseRepository->getExercises(),
            'equipment' => $this->exerciseRepository->getEquipment(),
            'muscleGroups' => $this->exerciseRepository->getMuscleGroups(),
            'messages' => $_GET['message'] ?? null
        ]);
    }

    public function createExercise(): void
    {
        $this->requireRole('admin');

        if (!$this->isPost()) {
            $this->redirect('/admin/exercises');
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $techniqueNotes = $this->nullableString($_POST['technique_notes'] ?? null);
        $difficulty = $_POST['difficulty'] ?? 'beginner';
        $videoUrl = $this->nullableString($_POST['video_url'] ?? null);
        $equipmentId = $this->nullableInt($_POST['equipment_id'] ?? null);
        $muscleGroupIds = $this->postedIds($_POST['muscle_group_ids'] ?? []);

        if ($name === '' || $description === '' || empty($muscleGroupIds) || !in_array($difficulty, ['beginner', 'intermediate', 'advanced'], true)) {
            $this->redirectWithMessage('/admin/exercises', 'Niepoprawne dane cwiczenia');
        }

        $slug = $this->uniqueSlug($name, fn (string $candidate) => $this->exerciseRepository->slugExists($candidate));

        $this->exerciseRepository->createExercise(
            $name,
            $slug,
            $description,
            $techniqueNotes,
            $difficulty,
            $videoUrl,
            $equipmentId,
            $muscleGroupIds
        );

        $this->redirectWithMessage('/admin/exercises', 'Cwiczenie dodane');
    }

    public function deactivateExercise(): void
    {
        $this->requireRole('admin');
        $exerciseId = $this->postedId();

        if ($exerciseId !== null) {
            $this->exerciseRepository->setExerciseActive($exerciseId, false);
        }

        $this->redirect('/admin/exercises');
    }

    public function activateExercise(): void
    {
        $this->requireRole('admin');
        $exerciseId = $this->postedId();

        if ($exerciseId !== null) {
            $this->exerciseRepository->setExerciseActive($exerciseId, true);
        }

        $this->redirect('/admin/exercises');
    }

    public function badges(): void
    {
        $this->requireRole('admin');

        $this->render('admin_badges', [
            'activeTab' => 'admin',
            'badges' => $this->badgeRepository->getBadges(),
            'exercises' => $this->exerciseRepository->getExercises(),
            'muscleGroups' => $this->exerciseRepository->getMuscleGroups(),
            'messages' => $_GET['message'] ?? null
        ]);
    }

    public function createBadge(): void
    {
        $this->requireRole('admin');

        if (!$this->isPost()) {
            $this->redirect('/admin/badges');
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'military_tech');
        $criteriaType = $_POST['criteria_type'] ?? 'custom';
        $targetValue = (float) ($_POST['target_value'] ?? 0);
        $exerciseId = $this->nullableInt($_POST['exercise_id'] ?? null);
        $muscleGroupId = $this->nullableInt($_POST['muscle_group_id'] ?? null);

        $allowedCriteria = ['exercise_weight', 'total_sessions', 'total_volume', 'muscle_sets', 'custom'];

        if ($name === '' || $description === '' || $targetValue <= 0 || !in_array($criteriaType, $allowedCriteria, true)) {
            $this->redirectWithMessage('/admin/badges', 'Niepoprawne dane odznaki');
        }

        if ($criteriaType === 'exercise_weight' && $exerciseId === null) {
            $this->redirectWithMessage('/admin/badges', 'Odznaka ciezaru wymaga cwiczenia');
        }

        if ($criteriaType === 'muscle_sets' && $muscleGroupId === null) {
            $this->redirectWithMessage('/admin/badges', 'Odznaka serii wymaga partii miesniowej');
        }

        $slug = $this->uniqueSlug($name, fn (string $candidate) => $this->badgeRepository->slugExists($candidate));

        $this->badgeRepository->createBadge(
            (int) $_SESSION['user_id'],
            $name,
            $slug,
            $description,
            $icon,
            $criteriaType,
            $targetValue,
            $exerciseId,
            $muscleGroupId
        );

        $this->redirectWithMessage('/admin/badges', 'Odznaka dodana');
    }

    public function deactivateBadge(): void
    {
        $this->requireRole('admin');
        $badgeId = $this->postedId();

        if ($badgeId !== null) {
            $this->badgeRepository->setBadgeActive($badgeId, false);
        }

        $this->redirect('/admin/badges');
    }

    public function activateBadge(): void
    {
        $this->requireRole('admin');
        $badgeId = $this->postedId();

        if ($badgeId !== null) {
            $this->badgeRepository->setBadgeActive($badgeId, true);
        }

        $this->redirect('/admin/badges');
    }

    private function canModifyUser(int $userId): bool
    {
        if ($userId === (int) $_SESSION['user_id']) {
            return false;
        }

        $user = $this->adminRepository->getUserById($userId);

        if (!$user) {
            return false;
        }

        return $user['role'] !== 'admin';
    }

    private function redirectWithMessage(string $path, string $message): void
    {
        $this->redirect($path.'?message='.rawurlencode($message));
    }

    private function postedId(): ?int
    {
        return $this->nullableInt($_POST['id'] ?? null);
    }

    private function postedIds(array $ids): array
    {
        $cleanIds = [];

        foreach ($ids as $id) {
            $cleanId = $this->nullableInt($id);

            if ($cleanId !== null && !in_array($cleanId, $cleanIds, true)) {
                $cleanIds[] = $cleanId;
            }
        }

        return $cleanIds;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        return $intValue === false ? null : (int) $intValue;
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function uniqueSlug(string $value, callable $exists): string
    {
        $baseSlug = $this->slugify($value);
        $slug = $baseSlug;
        $suffix = 2;

        while ($exists($slug)) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $value = strtr($value, [
            'Ą' => 'A',
            'Ć' => 'C',
            'Ę' => 'E',
            'Ł' => 'L',
            'Ń' => 'N',
            'Ó' => 'O',
            'Ś' => 'S',
            'Ż' => 'Z',
            'Ź' => 'Z',
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ż' => 'z',
            'ź' => 'z'
        ]);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim((string) $value, '-');

        return $value !== '' ? $value : 'element';
    }
}
