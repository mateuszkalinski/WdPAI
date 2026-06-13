<?php

require_once 'AppController.php';
require_once __DIR__.'/../services/ExerciseService.php';
require_once __DIR__.'/../services/WorkoutService.php';

class ApiController extends AppController
{
    private ?ExerciseService $exerciseService = null;
    private ?WorkoutService $workoutService = null;

    public function searchExercises(): void
    {
        $payload = $this->requireJsonPost();
        if ($payload === null) {
            return;
        }

        $this->jsonResponse([
            'exercises' => $this->exerciseService()->search($payload)
        ]);
    }

    public function startWorkoutSession(): void
    {
        $payload = $this->requireJsonPost();
        if ($payload === null) {
            return;
        }

        try {
            $this->jsonResponse($this->workoutService()->startSession((int) $_SESSION['user_id']), 201);
        } catch (Throwable) {
            $this->jsonResponse(['error' => 'Nie udalo sie rozpoczac sesji'], 500);
        }
    }

    public function addWorkoutSet(): void
    {
        $payload = $this->requireJsonPost();
        if ($payload === null) {
            return;
        }

        try {
            $this->jsonResponse($this->workoutService()->addSet((int) $_SESSION['user_id'], $payload), 201);
        } catch (InvalidArgumentException $exception) {
            $this->jsonResponse(['error' => $exception->getMessage()], 400);
        } catch (Throwable) {
            $this->jsonResponse(['error' => 'Nie udalo sie zapisac serii'], 500);
        }
    }

    public function skipWorkoutPlanItem(): void
    {
        $payload = $this->requireJsonPost();
        if ($payload === null) {
            return;
        }

        try {
            $this->jsonResponse($this->workoutService()->skipPlanItem((int) $_SESSION['user_id'], $payload));
        } catch (InvalidArgumentException $exception) {
            $this->jsonResponse(['error' => $exception->getMessage()], 400);
        } catch (Throwable) {
            $this->jsonResponse(['error' => 'Nie udalo sie pominac elementu planu'], 500);
        }
    }

    public function finishWorkoutSession(): void
    {
        $payload = $this->requireJsonPost();
        if ($payload === null) {
            return;
        }

        try {
            $result = $this->workoutService()->finishActiveSession((int) $_SESSION['user_id'], $payload);

            if ($result === null) {
                $this->jsonResponse(['error' => 'Brak aktywnej sesji do zakonczenia'], 400);
                return;
            }

            $this->jsonResponse($result);
        } catch (InvalidArgumentException $exception) {
            $this->jsonResponse(['error' => $exception->getMessage()], 400);
        } catch (Throwable) {
            $this->jsonResponse(['error' => 'Nie udalo sie zakonczyc sesji'], 500);
        }
    }

    private function requireJsonPost(): ?array
    {
        if (!$this->isLogged()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return null;
        }

        if (!$this->isPost()) {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return null;
        }

        if (!$this->isValidCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            $this->jsonResponse(['error' => 'Nieprawidlowy token bezpieczenstwa'], 400);
            return null;
        }

        return $this->readJsonPayload();
    }

    private function readJsonPayload(): ?array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

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

    private function exerciseService(): ExerciseService
    {
        return $this->exerciseService ??= new ExerciseService();
    }

    private function workoutService(): WorkoutService
    {
        return $this->workoutService ??= new WorkoutService();
    }
}
