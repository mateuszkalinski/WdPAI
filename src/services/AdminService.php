<?php

require_once __DIR__.'/../repositories/AdminRepository.php';
require_once __DIR__.'/../repositories/ExerciseRepository.php';
require_once __DIR__.'/../repositories/BadgeRepository.php';

class AdminService
{
    private AdminRepository $adminRepository;
    private ExerciseRepository $exerciseRepository;
    private BadgeRepository $badgeRepository;

    public function __construct(
        ?AdminRepository $adminRepository = null,
        ?ExerciseRepository $exerciseRepository = null,
        ?BadgeRepository $badgeRepository = null
    ) {
        $this->adminRepository = $adminRepository ?? new AdminRepository();
        $this->exerciseRepository = $exerciseRepository ?? new ExerciseRepository();
        $this->badgeRepository = $badgeRepository ?? new BadgeRepository();
    }

    public function usersData(?string $message = null): array
    {
        return [
            'activeTab' => 'admin',
            'users' => $this->adminRepository->getUsers(),
            'messages' => $message
        ];
    }

    public function exercisesData(?string $message = null): array
    {
        return [
            'activeTab' => 'admin',
            'exercises' => $this->exerciseRepository->getExercises(),
            'equipment' => $this->exerciseRepository->getEquipment(),
            'muscleGroups' => $this->exerciseRepository->getMuscleGroups(),
            'messages' => $message
        ];
    }

    public function badgesData(?string $message = null): array
    {
        return [
            'activeTab' => 'admin',
            'badges' => $this->badgeRepository->getBadges(),
            'exercises' => $this->exerciseRepository->getExercises(),
            'muscleGroups' => $this->exerciseRepository->getMuscleGroups(),
            'messages' => $message
        ];
    }

    public function blockUser(?int $userId, int $currentUserId): void
    {
        if ($userId !== null && $this->canModifyUser($userId, $currentUserId)) {
            $this->adminRepository->blockUser($userId);
        }
    }

    public function unblockUser(?int $userId, int $currentUserId): void
    {
        if ($userId !== null && $this->canModifyUser($userId, $currentUserId)) {
            $this->adminRepository->unblockUser($userId);
        }
    }

    public function deleteUser(?int $userId, int $currentUserId): void
    {
        if ($userId !== null && $this->canModifyUser($userId, $currentUserId)) {
            $this->adminRepository->deleteUser($userId);
        }
    }

    public function createExercise(array $input): string
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $techniqueNotes = $this->nullableString($input['technique_notes'] ?? null);
        $difficulty = (string) ($input['difficulty'] ?? 'beginner');
        $videoUrl = $this->nullableString($input['video_url'] ?? null);
        $equipmentId = $this->nullableInt($input['equipment_id'] ?? null);
        $muscleGroupIds = $this->postedIds($input['muscle_group_ids'] ?? []);

        if ($name === '' || $description === '' || empty($muscleGroupIds) || !in_array($difficulty, ['beginner', 'intermediate', 'advanced'], true)) {
            return 'Niepoprawne dane cwiczenia';
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

        return 'Cwiczenie dodane';
    }

    public function setExerciseActive(?int $exerciseId, bool $isActive): void
    {
        if ($exerciseId !== null) {
            $this->exerciseRepository->setExerciseActive($exerciseId, $isActive);
        }
    }

    public function createBadge(int $currentUserId, array $input): string
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $icon = trim((string) ($input['icon'] ?? 'military_tech'));
        $criteriaType = (string) ($input['criteria_type'] ?? 'custom');
        $targetValue = (float) ($input['target_value'] ?? 0);
        $exerciseId = $this->nullableInt($input['exercise_id'] ?? null);
        $muscleGroupId = $this->nullableInt($input['muscle_group_id'] ?? null);
        $allowedCriteria = ['exercise_weight', 'total_sessions', 'total_volume', 'muscle_sets', 'custom'];

        if ($name === '' || $description === '' || $targetValue <= 0 || !in_array($criteriaType, $allowedCriteria, true)) {
            return 'Niepoprawne dane odznaki';
        }

        if ($criteriaType === 'exercise_weight' && $exerciseId === null) {
            return 'Odznaka ciezaru wymaga cwiczenia';
        }

        if ($criteriaType === 'muscle_sets' && $muscleGroupId === null) {
            return 'Odznaka serii wymaga partii miesniowej';
        }

        $slug = $this->uniqueSlug($name, fn (string $candidate) => $this->badgeRepository->slugExists($candidate));

        $this->badgeRepository->createBadge(
            $currentUserId,
            $name,
            $slug,
            $description,
            $icon,
            $criteriaType,
            $targetValue,
            $exerciseId,
            $muscleGroupId
        );

        return 'Odznaka dodana';
    }

    public function setBadgeActive(?int $badgeId, bool $isActive): void
    {
        if ($badgeId !== null) {
            $this->badgeRepository->setBadgeActive($badgeId, $isActive);
        }
    }

    public function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        return $intValue === false ? null : (int) $intValue;
    }

    public function slugify(string $value): string
    {
        $asciiValue = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = strtolower($asciiValue !== false ? $asciiValue : $value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim((string) $value, '-');

        return $value !== '' ? $value : 'element';
    }

    private function canModifyUser(int $userId, int $currentUserId): bool
    {
        if ($userId === $currentUserId) {
            return false;
        }

        $user = $this->adminRepository->getUserById($userId);

        return $user && $user['role'] !== 'admin';
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
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
}
