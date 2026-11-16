<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/AdminActions.php';

class AdminController
{
    private AdminActions $actions;

    public function __construct(mysqli $conn)
    {
        $this->actions = new AdminActions($conn);
    }

    public function handle(): void
    {
        $enabled = getenv('ADMIN_ENABLED') === '1';
        $passwordHash = getenv('ADMIN_PASSWORD_HASH');

        // Не открываем панель, пока её явно не включили и не задали пароль.
        if (!$enabled || !is_string($passwordHash) || $passwordHash === '') {
            http_response_code(404);
            echo 'Страница не найдена';
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; " .
               "form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('habit_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/admin',
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Strict',
        ]);
        session_start();

        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        $isAuthenticated = $this->isAuthenticated($passwordHash);
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf = $_POST['csrf'] ?? null;
            if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
                http_response_code(403);
                echo 'Недействительный запрос. Обновите страницу.';
                return;
            }

            $action = $_POST['action'] ?? '';
            if (!is_string($action)) {
                http_response_code(400);
                echo 'Некорректный запрос';
                return;
            }

            if ($action === 'login' && !$isAuthenticated) {
                $password = $_POST['password'] ?? '';
                $lockUntil = (int) ($_SESSION['lock_until'] ?? 0);
                if (time() < $lockUntil) {
                    $error = 'Слишком много попыток. Попробуйте позже.';
                } elseif (is_string($password) && password_verify($password, $passwordHash)) {
                    session_regenerate_id();
                    $_SESSION['admin_authenticated'] = true;
                    $_SESSION['password_marker'] = hash('sha256', $passwordHash);
                    $_SESSION['last_active'] = time();
                    $_SESSION['failed_logins'] = 0;
                    $_SESSION['lock_until'] = 0;
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    $this->redirect();
                } else {
                    $_SESSION['failed_logins'] = (int) ($_SESSION['failed_logins'] ?? 0) + 1;
                    if ($_SESSION['failed_logins'] >= 5) {
                        $_SESSION['lock_until'] = time() + 60;
                        $_SESSION['failed_logins'] = 0;
                    }
                    $error = 'Неверный пароль.';
                }
            } elseif ($isAuthenticated) {
                if ($action === 'logout') {
                    $_SESSION = [];
                    session_regenerate_id();
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    $this->redirect();
                }

                $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);
                if ($userId === false) {
                    http_response_code(400);
                    echo 'Некорректный ID пользователя';
                    return;
                }

                $postedChallengeId = filter_var($_POST['challenge_id'] ?? null,
                    FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $challengeIdForRedirect = is_int($postedChallengeId) ? $postedChallengeId : null;
                try {
                    if (in_array($action, ['preview_streak', 'add_streak', 'clear_streak_preview'], true)) {
                        $challengeId = filter_var($_POST['challenge_id'] ?? null, FILTER_VALIDATE_INT,
                            ['options' => ['min_range' => 1]]);
                        if (!is_int($challengeId)) {
                            throw new DomainException('Выберите челлендж.');
                        }
                        $challengeIdForRedirect = $challengeId;
                        if ($action === 'preview_streak') {
                            $value = filter_var($_POST['streak_value'] ?? null, FILTER_VALIDATE_INT,
                                ['options' => ['min_range' => 0, 'max_range' => 3650]]);
                            if (!is_int($value)) {
                                throw new DomainException('Укажите число от 0 до 3650.');
                            }
                            $this->actions->setStreakPreview($userId, $challengeId, $value);
                        } elseif ($action === 'add_streak') {
                            $delta = filter_var($_POST['delta'] ?? null, FILTER_VALIDATE_INT);
                            if (!is_int($delta)) {
                                throw new DomainException('Некорректное изменение серии.');
                            }
                            $this->actions->addStreakPreview($userId, $challengeId, $delta);
                        } else {
                            $this->actions->clearStreakPreview($userId, $challengeId);
                        }
                        $_SESSION['notice'] = 'Стрик для просмотра обновлён.';
                    } elseif ($action === 'reset_checkin') {
                        if (!is_int($challengeIdForRedirect)) {
                            throw new DomainException('Выберите челлендж.');
                        }
                        $changed = $this->actions->resetChallengeCheckin($userId, $challengeIdForRedirect);
                        $_SESSION['notice'] = $changed
                            ? 'Отметка сброшена.' : 'Отметка уже сброшена.';
                    } elseif ($action === 'reset_all_checkins') {
                        $changed = $this->actions->resetAllCheckins($userId);
                        $_SESSION['notice'] = $changed
                            ? 'Отметки и награда сброшены.' : 'Сегодня нет отметок.';
                    } elseif ($action === 'reset_both') {
                        $changed = $this->actions->resetBoth($userId);
                        $_SESSION['notice'] = $changed
                            ? 'Отметки, награда и испытание сброшены.' : 'Сегодня всё уже сброшено.';
                    } elseif ($action === 'reset_task') {
                        $changed = $this->actions->resetTask($userId);
                        $_SESSION['notice'] = $changed
                            ? 'Выполнение испытания сброшено. Монеты списаны обратно.'
                            : 'Испытание сегодня ещё не выполнено.';
                    } elseif ($action === 'change_task') {
                        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT, [
                            'options' => ['min_range' => 1],
                        ]);
                        if ($taskId === false) {
                            throw new DomainException('Выберите испытание из списка.');
                        }
                        $this->actions->changeTask($userId, $taskId);
                        $_SESSION['notice'] = 'Испытание заменено только для выбранного пользователя. ' .
                            'Предыдущее выполнение за сегодня сброшено.';
                    } else {
                        throw new DomainException('Неизвестное действие.');
                    }
                } catch (DomainException $e) {
                    $_SESSION['notice_error'] = $e->getMessage();
                } catch (Throwable $e) {
                    error_log('Habit admin error: ' . (string) $e);
                    $_SESSION['notice_error'] = 'Ошибка операции. Проверьте журнал сервера.';
                }
                $this->redirect($userId, $challengeIdForRedirect);
            } else {
                http_response_code(403);
                $error = 'Для этого действия необходимо войти.';
            }
        }

        $csrf = $_SESSION['csrf'];
        $notice = $_SESSION['notice'] ?? null;
        $noticeError = $_SESSION['notice_error'] ?? null;
        unset($_SESSION['notice'], $_SESSION['notice_error']);

        $users = [];
        $tasks = [];
        $status = null;
        $selectedUserId = 0;
        $challenges = [];
        $selectedChallengeId = 0;
        $selectedChallenge = null;
        if ($isAuthenticated) {
            try {
                $users = $this->actions->getUsers();
                $tasks = $this->actions->getTasks();
                $requestedId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);
                $userIds = array_map('intval', array_column($users, 'id'));
                $selectedUserId = $requestedId && in_array($requestedId, $userIds, true)
                    ? $requestedId
                    : ($userIds[0] ?? 0);
                if ($selectedUserId > 0) {
                    $status = $this->actions->getStatus($selectedUserId);
                    $challenges = $this->actions->getChallenges($selectedUserId);
                    $requestedChallengeId = filter_input(INPUT_GET, 'challenge_id', FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 1]]);
                    $challengeIds = array_map('intval', array_column($challenges, 'id'));
                    $selectedChallengeId = $requestedChallengeId && in_array($requestedChallengeId, $challengeIds, true)
                        ? $requestedChallengeId : ($challengeIds[0] ?? 0);
                    foreach ($challenges as $challenge) {
                        if ((int) $challenge['id'] === $selectedChallengeId) {
                            $selectedChallenge = $challenge;
                            break;
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('Habit admin load error: ' . (string) $e);
                $noticeError = 'Не удалось загрузить данные. Проверьте структуру базы.';
            }
        }

        require __DIR__ . '/../views/admin.php';
    }

    private function isAuthenticated(string $passwordHash): bool
    {
        if (($_SESSION['admin_authenticated'] ?? false) !== true) {
            return false;
        }
        $marker = $_SESSION['password_marker'] ?? '';
        $lastActive = (int) ($_SESSION['last_active'] ?? 0);
        if (!is_string($marker)
            || !hash_equals(hash('sha256', $passwordHash), $marker)
            || time() - $lastActive > 1800
        ) {
            unset($_SESSION['admin_authenticated'], $_SESSION['password_marker']);
            return false;
        }
        $_SESSION['last_active'] = time();
        return true;
    }

    private function redirect(?int $userId = null, ?int $challengeId = null): never
    {
        $url = '/admin/';
        if ($userId !== null) {
            $url .= '?user_id=' . $userId;
            if ($challengeId !== null) {
                $url .= '&challenge_id=' . $challengeId;
            }
        }
        header('Location: ' . $url, true, 303);
        exit;
    }
}
