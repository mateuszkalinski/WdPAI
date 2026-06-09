<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/ExerciseRepository.php';

class ApiController extends AppController
{
    private ExerciseRepository $exerciseRepository;

    public function __construct()
    {
        $this->exerciseRepository = new ExerciseRepository();
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

        $contentType = $_SERVER["CONTENT_TYPE"] ?? '';

        if (stripos($contentType, 'application/json') === false) {
            $this->jsonResponse(['error' => 'Expected application/json'], 400);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);

        if (!is_array($payload)) {
            $this->jsonResponse(['error' => 'Invalid JSON'], 400);
            return;
        }

        $search = trim((string) ($payload['search'] ?? ''));
        $muscleGroupId = $this->nullableInt($payload['muscleGroupId'] ?? null);

        $this->jsonResponse([
            'exercises' => $this->exerciseRepository->searchExercises($search, $muscleGroupId)
        ]);
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
}
