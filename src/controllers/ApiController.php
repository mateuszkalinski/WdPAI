<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/ExerciseRepository.php';
require_once __DIR__.'/../repositories/WorkoutRepository.php';

class ApiController extends AppController
{
    private ExerciseRepository $exerciseRepository;
    private WorkoutRepository $workoutRepository;

    public function __construct()
    {
        $this->exerciseRepository = new ExerciseRepository();
        $this->workoutRepository = new WorkoutRepository();
    }

    public function searchExercises(): void
    {
        if (!$this->isLogged()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        if (!$this->isPost()) {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }

        $payload = $this->readJsonPayload();
        if ($payload === null) {
            return;
        }

        $search = trim((string) ($payload['search'] ?? ''));
        $muscleGroupId = $this->nullableInt($payload['muscleGroupId'] ?? null);

        $this->jsonResponse([
            'exercises' => $this->exerciseRepository->searchExercises($search, $muscleGroupId)
        ]);
    }

    public function startWorkoutSession(): void
    {
        if (!$this->isLogged()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        if (!$this->isPost()) {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }

        $payload = $this->readJsonPayload();
        if ($payload === null) {
            return;
        }

        try {
            $session = $this->workoutRepository->startSession((int) $_SESSION['user_id']);

            $this->jsonResponse([
                'session' => $session,
                'sets' => $this->workoutRepository->getSetsForSession((int) $_SESSION['user_id'], (int) $session['id'])
            ], 201);
        } catch (Throwable) {
            $this->jsonResponse(['error' => 'Nie udalo sie rozpoczac sesji'], 500);
        }
    }

    public function addWorkoutSet(): void
    {
        if (!$this->isLogged()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        if (!$this->isPost()) {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }

        $payload = $this->readJsonPayload();
        if ($payload === null) {
            return;
        }

        $exerciseId = $this->positiveInt($payload['exerciseId'] ?? null);
        $weightKg = $this->nonNegativeFloat($payload['weightKg'] ?? null);
        $reps = $this->positiveInt($payload['reps'] ?? null);
        $rpe = $this->nullableFloat($payload['rpe'] ?? null);
        $setType = (string) ($payload['setType'] ?? 'working');
        $note = trim((string) ($payload['note'] ?? ''));
        $note = $note === '' ? null : substr($note, 0, 500);

        if ($exerciseId === null || $weightKg === null || $reps === null) {
            $this->jsonResponse(['error' => 'Podaj cwiczenie, ciezar i liczbe powtorzen'], 400);
            return;
        }

        if ($rpe !== null && ($rpe < 0 || $rpe > 10)) {
            $this->jsonResponse(['error' => 'RPE musi byc w zakresie 0-10'], 400);
            return;
        }

        if (!in_array($setType, ['warmup', 'working', 'drop', 'failure'], true)) {
            $this->jsonResponse(['error' => 'Nieprawidlowy typ serii'], 400);
            return;
        }

        try {
            $result = $this->workoutRepository->addSet(
                (int) $_SESSION['user_id'],
                $exerciseId,
                $weightKg,
                $reps,
                $rpe,
                $setType,
                $note
            );

            $this->jsonResponse($result, 201);
        } catch (InvalidArgumentException $exception) {
            $this->jsonResponse(['error' => $exception->getMessage()], 400);
        } catch (Throwable) {
            $this->jsonResponse(['error' => 'Nie udalo sie zapisac serii'], 500);
        }
    }

    public function finishWorkoutSession(): void
    {
        if (!$this->isLogged()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        if (!$this->isPost()) {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }

        $payload = $this->readJsonPayload();
        if ($payload === null) {
            return;
        }

        $sessionRpe = $this->nullableFloat($payload['sessionRpe'] ?? null);
        $notes = trim((string) ($payload['notes'] ?? ''));
        $notes = $notes === '' ? null : substr($notes, 0, 1000);

        if ($sessionRpe !== null && ($sessionRpe < 0 || $sessionRpe > 10)) {
            $this->jsonResponse(['error' => 'RPE sesji musi byc w zakresie 0-10'], 400);
            return;
        }

        try {
            $result = $this->workoutRepository->finishActiveSession((int) $_SESSION['user_id'], $sessionRpe, $notes);

            if ($result === null) {
                $this->jsonResponse(['error' => 'Brak aktywnej sesji do zakonczenia'], 400);
                return;
            }

            $this->jsonResponse($result);
        } catch (Throwable) {
            $this->jsonResponse(['error' => 'Nie udalo sie zakonczyc sesji'], 500);
        }
    }

    private function readJsonPayload(): ?array
    {
        $contentType = $_SERVER["CONTENT_TYPE"] ?? '';

        if (stripos($contentType, 'application/json') === false) {
            $this->jsonResponse(['error' => 'Expected application/json'], 400);
            return null;
        }

        $payload = json_decode(file_get_contents('php://input'), true);

        if (!is_array($payload)) {
            $this->jsonResponse(['error' => 'Invalid JSON'], 400);
            return null;
        }

        return $payload;
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        return $intValue === false ? null : (int) $intValue;
    }

    private function positiveInt($value): ?int
    {
        $intValue = $this->nullableInt($value);
        return $intValue !== null && $intValue > 0 ? $intValue : null;
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
