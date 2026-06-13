<?php

require_once __DIR__.'/../repositories/HistoryRepository.php';

class HistoryService
{
    private HistoryRepository $historyRepository;

    public function __construct(?HistoryRepository $historyRepository = null)
    {
        $this->historyRepository = $historyRepository ?? new HistoryRepository();
    }

    public function historyData(int $userId): array
    {
        $sessions = $this->historyRepository->getSessions($userId);

        return [
            'title' => 'Historia',
            'activeTab' => 'history',
            'summary' => $this->historyRepository->getHistorySummary($userId),
            'sessions' => $sessions,
            'setsBySession' => $this->historyRepository->getSetsForSessions($userId, array_column($sessions, 'id')),
            'records' => $this->historyRepository->getExerciseRecords($userId)
        ];
    }
}
