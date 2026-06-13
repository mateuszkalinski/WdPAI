<?php

require_once __DIR__.'/../repositories/PlannerRepository.php';
require_once __DIR__.'/../repositories/ExerciseRepository.php';

class PlannerService
{
    private PlannerRepository $plannerRepository;
    private ExerciseRepository $exerciseRepository;

    public function __construct(?PlannerRepository $plannerRepository = null, ?ExerciseRepository $exerciseRepository = null)
    {
        $this->plannerRepository = $plannerRepository ?? new PlannerRepository();
        $this->exerciseRepository = $exerciseRepository ?? new ExerciseRepository();
    }

    public function plannerData(int $userId, ?string $plannerMessage = null): array
    {
        $activePlan = $this->plannerRepository->getActivePlan($userId);

        return [
            'title' => 'Planer',
            'activeTab' => 'planer',
            'activePlan' => $activePlan,
            'availablePlans' => $this->plannerRepository->getAvailablePlans($userId),
            'planDays' => $activePlan ? $this->plannerRepository->getPlanDays((int) $activePlan['id']) : [],
            'planExercises' => $activePlan ? $this->plannerRepository->getPlanExercises((int) $activePlan['id']) : [],
            'muscleVolume' => $activePlan ? $this->plannerRepository->getPlanVolumeByMuscle((int) $activePlan['id']) : [],
            'exercises' => $this->exerciseRepository->getActiveExercises(),
            'plannerMessage' => $plannerMessage
        ];
    }

    public function activatePlan(int $userId, $planId): string
    {
        $planId = $this->positiveInt($planId);

        if ($planId === null) {
            return 'Nie wybrano planu.';
        }

        return $this->plannerRepository->activatePlan($userId, $planId)
            ? 'Plan zostal ustawiony jako aktywny.'
            : 'Nie mozesz uzyc tego planu.';
    }

    public function addExerciseToActivePlan(int $userId, array $input): string
    {
        $dayOrder = $this->positiveInt($input['day_order'] ?? null);
        $exerciseId = $this->positiveInt($input['exercise_id'] ?? null);
        $targetSets = $this->positiveInt($input['target_sets'] ?? 3);
        $targetRepsMin = $this->positiveInt($input['target_reps_min'] ?? 8);
        $targetRepsMax = $this->positiveInt($input['target_reps_max'] ?? 10);

        if (
            $dayOrder === null ||
            $exerciseId === null ||
            $targetSets === null ||
            $targetRepsMin === null ||
            $targetRepsMax === null ||
            $targetRepsMax < $targetRepsMin
        ) {
            return 'Sprawdz dane cwiczenia przed dodaniem.';
        }

        try {
            $this->plannerRepository->addExerciseToActivePlan(
                $userId,
                $dayOrder,
                $exerciseId,
                $targetSets,
                $targetRepsMin,
                $targetRepsMax
            );

            return 'Cwiczenie dodane do aktywnego planu.';
        } catch (Throwable) {
            return 'Nie udalo sie dodac cwiczenia.';
        }
    }

    public function createEmptyPlan(int $userId, array $input): string
    {
        $name = trim((string) ($input['name'] ?? ''));
        $daysCount = $this->positiveInt($input['days_count'] ?? null);
        $rawDayNames = is_array($input['day_names'] ?? null) ? $input['day_names'] : [];

        if ($name === '' || strlen($name) > 120 || $daysCount === null || $daysCount > 7) {
            return 'Podaj nazwe planu i liczbe dni od 1 do 7.';
        }

        $dayNames = [];
        for ($index = 0; $index < $daysCount; $index++) {
            $dayName = trim((string) ($rawDayNames[$index] ?? ''));
            $dayNames[] = $dayName !== '' ? substr($dayName, 0, 120) : 'Dzien '.($index + 1);
        }

        try {
            $this->plannerRepository->createEmptyPlan($userId, substr($name, 0, 120), $dayNames);
            return 'Nowy pusty plan zostal utworzony i ustawiony jako aktywny.';
        } catch (Throwable) {
            return 'Nie udalo sie utworzyc planu.';
        }
    }

    public function makeActivePlanEditable(int $userId): string
    {
        try {
            $this->plannerRepository->makeActivePlanEditable($userId);
            return 'Utworzono prywatna kopie planu do edycji.';
        } catch (Throwable) {
            return 'Nie udalo sie przygotowac planu do edycji.';
        }
    }

    public function updatePlanExercise(int $userId, array $input): string
    {
        if (is_array($input['exercise_ids'] ?? null)) {
            return $this->updatePlanExercises($userId, $input);
        }

        $planExerciseId = $this->positiveInt($input['plan_exercise_id'] ?? null);
        $targetSets = $this->positiveInt($input['target_sets'] ?? null);
        $targetRepsMin = $this->positiveInt($input['target_reps_min'] ?? null);
        $targetRepsMax = $this->positiveInt($input['target_reps_max'] ?? null);

        if (
            $planExerciseId === null ||
            $targetSets === null ||
            $targetRepsMin === null ||
            $targetRepsMax === null ||
            $targetRepsMax < $targetRepsMin
        ) {
            return 'Sprawdz serie i zakres powtorzen.';
        }

        return $this->plannerRepository->updatePlanExercise($userId, $planExerciseId, $targetSets, $targetRepsMin, $targetRepsMax)
            ? 'Cwiczenie w planie zostalo zaktualizowane.'
            : 'Mozesz edytowac tylko wlasny plan.';
    }

    private function updatePlanExercises(int $userId, array $input): string
    {
        $ids = is_array($input['exercise_ids'] ?? null) ? $input['exercise_ids'] : [];
        $setsById = is_array($input['target_sets'] ?? null) ? $input['target_sets'] : [];
        $repsMinById = is_array($input['target_reps_min'] ?? null) ? $input['target_reps_min'] : [];
        $repsMaxById = is_array($input['target_reps_max'] ?? null) ? $input['target_reps_max'] : [];
        $updates = [];

        foreach ($ids as $rawId) {
            $id = $this->positiveInt($rawId);
            $targetSets = $id ? $this->positiveInt($setsById[$id] ?? null) : null;
            $targetRepsMin = $id ? $this->positiveInt($repsMinById[$id] ?? null) : null;
            $targetRepsMax = $id ? $this->positiveInt($repsMaxById[$id] ?? null) : null;

            if (
                $id === null ||
                $targetSets === null ||
                $targetRepsMin === null ||
                $targetRepsMax === null ||
                $targetRepsMax < $targetRepsMin
            ) {
                return 'Sprawdz serie i zakres powtorzen.';
            }

            $updates[] = [
                'id' => $id,
                'target_sets' => $targetSets,
                'target_reps_min' => $targetRepsMin,
                'target_reps_max' => $targetRepsMax
            ];
        }

        if (empty($updates)) {
            return 'Brak cwiczen do zapisania.';
        }

        return $this->plannerRepository->updatePlanExercises($userId, $updates)
            ? 'Plan zostal zaktualizowany.'
            : 'Mozesz edytowac tylko wlasny plan.';
    }

    public function movePlanExercise(int $userId, array $input): string
    {
        $planExerciseId = $this->positiveInt($input['plan_exercise_id'] ?? null);
        $direction = (string) ($input['direction'] ?? '');

        if ($planExerciseId === null || !in_array($direction, ['up', 'down'], true)) {
            return 'Nie wybrano poprawnego ruchu.';
        }

        return $this->plannerRepository->movePlanExercise($userId, $planExerciseId, $direction)
            ? 'Kolejnosc cwiczen zostala zmieniona.'
            : 'Nie mozna przesunac tego cwiczenia.';
    }

    public function deletePlanExercise(int $userId, $planExerciseId): string
    {
        $planExerciseId = $this->positiveInt($planExerciseId);

        if ($planExerciseId === null) {
            return 'Nie wybrano cwiczenia do usuniecia.';
        }

        return $this->plannerRepository->deletePlanExercise($userId, $planExerciseId)
            ? 'Cwiczenie usuniete z planu.'
            : 'Mozesz usuwac cwiczenia tylko z wlasnego planu.';
    }

    public function deletePlan(int $userId, $planId): string
    {
        $planId = $this->positiveInt($planId);

        if ($planId === null) {
            return 'Nie wybrano planu do usuniecia.';
        }

        return $this->plannerRepository->deletePlan($userId, $planId)
            ? 'Plan zostal usuniety.'
            : 'Mozesz usuwac tylko wlasne plany.';
    }

    private function positiveInt($value): ?int
    {
        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        return $intValue !== false && $intValue > 0 ? (int) $intValue : null;
    }

}
