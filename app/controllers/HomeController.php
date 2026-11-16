<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/DailyReward.php';
require_once __DIR__ . '/../models/DailyTask.php';
require_once __DIR__ . '/../models/Challenge.php';
require_once __DIR__ . '/../models/ActivityHistory.php';

class HomeController
{
    private User $userModel;
    private DailyReward $rewardModel;
    private DailyTask $taskModel;
    private Challenge $challengeModel;
    private ActivityHistory $historyModel;
    private const USER_ID = 1; // Учебный сайт пока работает с одним аккаунтом.

    public function __construct(mysqli $conn)
    {
        $this->userModel = new User($conn);
        $this->rewardModel = new DailyReward($conn);
        $this->taskModel = new DailyTask($conn);
        $this->challengeModel = new Challenge($conn);
        $this->historyModel = new ActivityHistory($conn);
    }

    public function index(): void
    {
        $user = $this->userModel->getById(self::USER_ID);
        if ($user === null) {
            http_response_code(404);
            echo 'Пользователь не найден';
            return;
        }

        $challenges = $this->challengeModel->listForUser(self::USER_ID);
        $activeChallenge = $this->challengeModel->getActive(self::USER_ID);
        $claimedToday = $this->rewardModel->wasClaimedToday(self::USER_ID);
        $todayTask = $this->taskModel->getToday(self::USER_ID);
        $completedToday = $this->taskModel->wasCompletedToday(self::USER_ID);
        $csrf = $_SESSION['site_csrf'];
        $noticeError = $_SESSION['site_error'] ?? null;
        unset($_SESSION['site_error']);

        require __DIR__ . '/../views/home.php';
    }

    public function history(): void
    {
        $user = $this->userModel->getById(self::USER_ID);
        if ($user === null) {
            http_response_code(404);
            echo 'Пользователь не найден';
            return;
        }

        $challenges = $this->challengeModel->listForUser(self::USER_ID);
        $activityHistory = $this->historyModel->getForUser(self::USER_ID);
        require __DIR__ . '/../views/history.php';
    }

    private function execute(callable $operation): never
    {
        try {
            $operation();
        } catch (DomainException $e) {
            $_SESSION['site_error'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Habit site error: ' . (string) $e);
            $_SESSION['site_error'] = 'Не удалось выполнить действие.';
        }
        header('Location: /', true, 303);
        exit;
    }

    public function completeTask(): never
    {
        $this->execute(fn () => $this->taskModel->complete(self::USER_ID));
    }

    public function updateChallengeName(): never
    {
        $this->execute(function (): void {
            $title = $_POST['challenge_name'] ?? null;
            if (!is_string($title)) {
                throw new DomainException('Некорректное название.');
            }
            $this->challengeModel->renameActive(self::USER_ID, $title);
        });
    }

    public function createChallenge(): never
    {
        $this->execute(function (): void {
            $title = $_POST['challenge_name'] ?? null;
            if (!is_string($title)) {
                throw new DomainException('Некорректное название.');
            }
            $this->challengeModel->create(self::USER_ID, $title);
        });
    }

    public function selectChallenge(): never
    {
        $this->execute(function (): void {
            $challengeId = filter_var($_POST['challenge_id'] ?? null,
                FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($challengeId === false || $challengeId === null) {
                throw new DomainException('Не выбран челлендж.');
            }
            $this->challengeModel->select(self::USER_ID, $challengeId);
        });
    }

    public function completeChallenge(): never
    {
        $this->execute(function (): void {
            $challengeId = filter_var($_POST['challenge_id'] ?? null,
                FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!is_int($challengeId)) {
                throw new DomainException('Не выбран челлендж.');
            }
            $this->challengeModel->complete(self::USER_ID, $challengeId);
        });
    }
}
