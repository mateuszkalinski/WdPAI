<?php

require_once __DIR__.'/../repositories/WorkoutRepository.php';

class WorkoutService
{
    private WorkoutRepository $workoutRepository;

    public function __construct(?WorkoutRepository $workoutRepository = null)
    {
        $this->workoutRepository = $workoutRepository ?? new WorkoutRepository();
    }

    public function sessionData(int $userId, array $exercises): array
    {
        $activeSession = $this->workoutRepository->getActiveSession($userId);
        $plannedWorkout = $activeSession
            ? $this->workoutRepository->getPlannedWorkoutForSession($userId, (int) $activeSession['id'])
            : $this->workoutRepository->getPlannedWorkoutPreview($userId);

        return [
            'title' => 'Sesja treningowa',
            'activeTab' => 'session',
            'exercises' => $exercises,
            'activeSession' => $activeSession,
            'plannedWorkout' => $plannedWorkout,
            'sets' => $activeSession ? $this->workoutRepository->getSetsForSession($userId, (int) $activeSession['id']) : []
        ];
    }

    public function startSession(int $userId): array
    {
        $session = $this->workoutRepository->startSession($userId);
        $sessionId = (int) $session['id'];

        return [
            'session' => $session,
            'plannedWorkout' => $this->workoutRepository->getPlannedWorkoutForSession($userId, $sessionId),
            'sets' => $this->workoutRepository->getSetsForSession($userId, $sessionId)
        ];
    }

    public function addSet(int $userId, array $payload): array
    {
        $exerciseId = $this->positiveInt($payload['exerciseId'] ?? null);
        $weightKg = $this->nonNegativeFloat($payload['weightKg'] ?? null);
        $reps = $this->positiveInt($payload['reps'] ?? null);
        $rpe = $this->nullableFloat($payload['rpe'] ?? null);
        $setType = 'working';
        $note = trim((string) ($payload['note'] ?? ''));
        $note = $note === '' ? null : substr($note, 0, 500);

        if ($exerciseId === null || $weightKg === null || $reps === null) {
            throw new InvalidArgumentException('Podaj cwiczenie, ciezar i liczbe powtorzen');
        }

        if ($rpe !== null && ($rpe < 0 || $rpe > 10)) {
            throw new InvalidArgumentException('RPE musi byc w zakresie 0-10');
        }

        $result = $this->workoutRepository->addSet($userId, $exerciseId, $weightKg, $reps, $rpe, $setType, $note);
        $sessionId = (int) ($result['session']['id'] ?? 0);

        if ($sessionId > 0) {
            $result['plannedWorkout'] = $this->workoutRepository->getPlannedWorkoutForSession($userId, $sessionId);
        }

        return $result;
    }

    public function skipPlanItem(int $userId, array $payload): array
    {
        $planExerciseId = $this->positiveInt($payload['planExerciseId'] ?? null);

        if ($planExerciseId === null) {
            throw new InvalidArgumentException('Brak cwiczenia z planu do pominiecia');
        }

        $result = $this->workoutRepository->skipPlannedItem($userId, $planExerciseId);
        $sessionId = (int) ($result['session']['id'] ?? 0);

        if ($sessionId > 0) {
            $result['plannedWorkout'] = $this->workoutRepository->getPlannedWorkoutForSession($userId, $sessionId);
            $result['sets'] = $this->workoutRepository->getSetsForSession($userId, $sessionId);
        }

        return $result;
    }

    public function finishActiveSession(int $userId, array $payload): ?array
    {
        $sessionRpe = $this->nullableFloat($payload['sessionRpe'] ?? null);
        $notes = trim((string) ($payload['notes'] ?? ''));
        $notes = $notes === '' ? null : substr($notes, 0, 1000);

        if ($sessionRpe !== null && ($sessionRpe < 0 || $sessionRpe > 10)) {
            throw new InvalidArgumentException('RPE sesji musi byc w zakresie 0-10');
        }

        $result = $this->workoutRepository->finishActiveSession($userId, $sessionRpe, $notes);

        if ($result !== null) {
            $sessionId = (int) ($result['session']['id'] ?? 0);

            if ($sessionId > 0) {
                $result['plannedWorkout'] = $this->workoutRepository->getPlannedWorkoutForSession($userId, $sessionId);
            }
        }

        return $result;
    }

    private function positiveInt($value): ?int
    {
        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        return $intValue !== false && $intValue > 0 ? (int) $intValue : null;
    }

    private function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function nonNegativeFloat($value): ?float
    {
        $floatValue = $this->nullableFloat($value);
        return $floatValue !== null && $floatValue >= 0 ? $floatValue : null;
    }
}
