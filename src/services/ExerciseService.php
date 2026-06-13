<?php

require_once __DIR__.'/../repositories/ExerciseRepository.php';

class ExerciseService
{
    private ExerciseRepository $exerciseRepository;

    public function __construct(?ExerciseRepository $exerciseRepository = null)
    {
        $this->exerciseRepository = $exerciseRepository ?? new ExerciseRepository();
    }

    public function atlasData(): array
    {
        return [
            'activeTab' => 'atlas',
            'exercises' => $this->exerciseRepository->getActiveExercises(),
            'muscleGroups' => $this->exerciseRepository->getMuscleGroups()
        ];
    }

    public function activeExercises(): array
    {
        return $this->exerciseRepository->getActiveExercises();
    }

    public function search(array $payload): array
    {
        $search = trim((string) ($payload['search'] ?? ''));
        $muscleGroupId = $this->nullableInt($payload['muscleGroupId'] ?? null);

        return $this->exerciseRepository->searchExercises($search, $muscleGroupId);
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        return $intValue === false ? null : (int) $intValue;
    }
}
