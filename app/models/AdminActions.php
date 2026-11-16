<?php

declare(strict_types=1);

/**
 * Операции тестовой админ-панели.
 * Каждая операция списания монет выполняется внутри транзакции с блокировкой
 * строки пользователя — так она согласована с обычной выдачей наград.
 */
class AdminActions
{
    public function __construct(private mysqli $conn)
    {
    }

    public function getUsers(): array
    {
        $result = $this->conn->query(
            'SELECT id, challenge_name, coins, current_streak, best_streak
             FROM users ORDER BY id LIMIT 100'
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getTasks(): array
    {
        $result = $this->conn->query(
            'SELECT id, title, description, coins_reward
             FROM tasks ORDER BY id'
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getStatus(int $userId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT u.id, u.challenge_name, u.coins,
                    u.current_streak, u.best_streak,
                    (SELECT COUNT(*) FROM reward_history r
                     WHERE r.user_id = u.id AND r.reward_date = CURDATE()) AS reward_done,
                    (SELECT COUNT(*) FROM task_completions c
                     WHERE c.user_id = u.id AND c.completion_date = CURDATE()) AS task_done,
                    (SELECT task_id FROM user_task_overrides o
                     WHERE o.user_id = u.id AND o.task_date = CURDATE()) AS override_task_id,
                    (SELECT task_id FROM daily_task_schedule s
                     WHERE s.task_date = CURDATE()) AS global_task_id,
                    (SELECT COUNT(*) FROM challenge_checkins ci
                     JOIN challenges c ON c.id = ci.challenge_id
                     WHERE c.user_id = u.id AND ci.completion_date = CURDATE()) AS checkins_today,
                    CURDATE() AS today
             FROM users u WHERE u.id = ?'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $status = $stmt->get_result()->fetch_assoc();
        if ($status === null) {
            throw new DomainException('Пользователь не найден.');
        }
        return $status;
    }

    public function getChallenges(int $userId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, title, current_streak, best_streak, preview_streak,
                    (last_completed_date = CURDATE()) AS done_today,
                    CASE WHEN last_completed_date >= CURDATE() - INTERVAL 1 DAY
                         THEN current_streak ELSE 0 END AS real_display_streak
             FROM challenges WHERE user_id = ? ORDER BY id'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function lockOwnedChallenge(int $userId, int $challengeId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, preview_streak, current_streak,
                    CASE WHEN last_completed_date >= CURDATE() - INTERVAL 1 DAY
                         THEN current_streak ELSE 0 END AS real_display_streak
             FROM challenges WHERE id = ? AND user_id = ? FOR UPDATE'
        );
        $stmt->bind_param('ii', $challengeId, $userId);
        $stmt->execute();
        $challenge = $stmt->get_result()->fetch_assoc();
        if ($challenge === null) {
            throw new DomainException('Челлендж не найден.');
        }
        return $challenge;
    }

    public function setStreakPreview(int $userId, int $challengeId, int $value): void
    {
        if ($value < 0 || $value > 3650) {
            throw new DomainException('Укажите число от 0 до 3650.');
        }
        $this->conn->begin_transaction();
        try {
            $this->lockOwnedChallenge($userId, $challengeId);
            $stmt = $this->conn->prepare(
                'UPDATE challenges SET preview_streak = ? WHERE id = ? AND user_id = ?'
            );
            $stmt->bind_param('iii', $value, $challengeId, $userId);
            $stmt->execute();
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function addStreakPreview(int $userId, int $challengeId, int $amount): void
    {
        if (!in_array($amount, [1, 7], true)) {
            throw new DomainException('Некорректное изменение серии.');
        }
        $this->conn->begin_transaction();
        try {
            $challenge = $this->lockOwnedChallenge($userId, $challengeId);
            $current = (int) ($challenge['preview_streak'] ?? $challenge['real_display_streak']);
            $value = min(3650, $current + $amount);
            $stmt = $this->conn->prepare(
                'UPDATE challenges SET preview_streak = ? WHERE id = ? AND user_id = ?'
            );
            $stmt->bind_param('iii', $value, $challengeId, $userId);
            $stmt->execute();
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function clearStreakPreview(int $userId, int $challengeId): void
    {
        $this->conn->begin_transaction();
        try {
            $this->lockOwnedChallenge($userId, $challengeId);
            $stmt = $this->conn->prepare(
                'UPDATE challenges SET preview_streak = NULL WHERE id = ? AND user_id = ?'
            );
            $stmt->bind_param('ii', $challengeId, $userId);
            $stmt->execute();
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    private function lockUser(int $userId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, coins FROM users WHERE id = ? FOR UPDATE'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user === null) {
            throw new DomainException('Пользователь не найден.');
        }
        return $user;
    }

    /** Сбросить выполнение конкретного челленджа за сегодня. */
    public function resetChallengeCheckin(int $userId, int $challengeId): bool
    {
        $this->conn->begin_transaction();
        try {
            $this->lockUser($userId);
            $changed = $this->resetCheckinInTransaction($userId, $challengeId);
            // Первую отметку дня оплачиваем один раз на аккаунт. Если больше
            // не осталось отметок, отменяем и общую награду.
            if ($changed && !$this->hasAnyCheckinToday($userId)) {
                $this->resetRewardInTransaction($userId, $this->lockUser($userId));
            }
            $this->conn->commit();
            return $changed;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /** Сбросить все отметки пользователя за сегодня вместе с наградой. */
    public function resetAllCheckins(int $userId): bool
    {
        $this->conn->begin_transaction();
        try {
            $this->lockUser($userId);
            $changed = $this->resetAllCheckinsInTransaction($userId);
            $rewardChanged = $this->resetRewardInTransaction($userId, $this->lockUser($userId));
            $this->conn->commit();
            return $changed || $rewardChanged;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /** Сбросить все отметки, награду и общее испытание за одну транзакцию. */
    public function resetBoth(int $userId): bool
    {
        $this->conn->begin_transaction();
        try {
            $this->lockUser($userId);
            $checkinsChanged = $this->resetAllCheckinsInTransaction($userId);
            $rewardChanged = $this->resetRewardInTransaction($userId, $this->lockUser($userId));
            $taskChanged = $this->resetTaskInTransaction($userId, $this->lockUser($userId));
            $this->conn->commit();
            return $checkinsChanged || $rewardChanged || $taskChanged;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    private function resetAllCheckinsInTransaction(int $userId): bool
    {
        $stmt = $this->conn->prepare(
            'SELECT id FROM challenges WHERE user_id = ? ORDER BY id'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $ids = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
        $changed = false;
        foreach ($ids as $challengeId) {
            if ($this->resetCheckinInTransaction($userId, (int) $challengeId)) {
                $changed = true;
            }
        }
        return $changed;
    }

    private function hasAnyCheckinToday(int $userId): bool
    {
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) AS total FROM challenge_checkins ci
             JOIN challenges c ON c.id = ci.challenge_id
             WHERE c.user_id = ? AND ci.completion_date = CURDATE()'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_assoc()['total'] > 0;
    }

    /** Требует блокировки пользователя до вызова. */
    private function resetCheckinInTransaction(int $userId, int $challengeId): bool
    {
        $stmt = $this->conn->prepare(
            'SELECT id FROM challenges WHERE id = ? AND user_id = ? FOR UPDATE'
        );
        $stmt->bind_param('ii', $challengeId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc() === null) {
            throw new DomainException('Челлендж не найден.');
        }
        $stmt = $this->conn->prepare(
            'SELECT id FROM challenge_checkins
             WHERE challenge_id = ? AND completion_date = CURDATE() FOR UPDATE'
        );
        $stmt->bind_param('i', $challengeId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc() === null) {
            return false;
        }
        $stmt = $this->conn->prepare(
            'DELETE FROM challenge_checkins
             WHERE challenge_id = ? AND completion_date = CURDATE()'
        );
        $stmt->bind_param('i', $challengeId);
        $stmt->execute();

        $stmt = $this->conn->prepare(
            'SELECT completion_date FROM challenge_checkins
             WHERE challenge_id = ? ORDER BY completion_date ASC'
        );
        $stmt->bind_param('i', $challengeId);
        $stmt->execute();
        $dates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $streak = 0;
        $best = 0;
        $lastDate = null;
        foreach ($dates as $row) {
            $date = $row['completion_date'];
            $yesterday = (new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
            $streak = $lastDate === $yesterday ? $streak + 1 : 1;
            $best = max($best, $streak);
            $lastDate = $date;
        }
        $stmt = $this->conn->prepare(
            'UPDATE challenges SET current_streak = ?, best_streak = ?,
                    last_completed_date = ?, preview_streak = NULL
             WHERE id = ? AND user_id = ?'
        );
        $stmt->bind_param('iisii', $streak, $best, $lastDate, $challengeId, $userId);
        $stmt->execute();
        return true;
    }

    private function resetRewardInTransaction(int $userId, array $user): bool
    {
        $stmt = $this->conn->prepare(
            'SELECT coins_earned FROM reward_history
             WHERE user_id = ? AND reward_date = CURDATE() FOR UPDATE'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();
        if ($record === null) {
            return false;
        }
        $earned = (int) $record['coins_earned'];
        $this->assertEnoughCoins($user, $earned);

        $stmt = $this->conn->prepare(
            'DELETE FROM reward_history
             WHERE user_id = ? AND reward_date = CURDATE()'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();

        // Восстанавливаем серию и рекорд из оставшейся истории.
        $stmt = $this->conn->prepare(
            'SELECT reward_date FROM reward_history
             WHERE user_id = ? ORDER BY reward_date ASC'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $dates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $streak = 0;
        $best = 0;
        $lastDate = null;
        foreach ($dates as $row) {
            $date = $row['reward_date'];
            $yesterday = (new DateTimeImmutable($date))
                ->modify('-1 day')->format('Y-m-d');
            $streak = ($lastDate === $yesterday) ? $streak + 1 : 1;
            $best = max($best, $streak);
            $lastDate = $date;
        }

        $stmt = $this->conn->prepare(
            'UPDATE users
             SET coins = coins - ?, current_streak = ?,
                 best_streak = ?, last_claim_date = ?
             WHERE id = ?'
        );
        $stmt->bind_param('iiisi', $earned, $streak, $best, $lastDate, $userId);
        $stmt->execute();
        return true;
    }

    /** Отменить сегодняшнее выполнение и списать ранее начисленные монеты. */
    public function resetTask(int $userId): bool
    {
        $this->conn->begin_transaction();
        try {
            $user = $this->lockUser($userId);
            $reset = $this->resetTaskInTransaction($userId, $user);
            $this->conn->commit();
            return $reset;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * Индивидуально назначить другое задание и сбросить выполнение за сегодня.
     * Других пользователей и общее расписание не изменяем.
     */
    public function changeTask(int $userId, int $taskId): void
    {
        $this->conn->begin_transaction();
        try {
            $user = $this->lockUser($userId);
            $stmt = $this->conn->prepare('SELECT id FROM tasks WHERE id = ?');
            $stmt->bind_param('i', $taskId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc() === null) {
                throw new DomainException('Такого испытания нет.');
            }

            // Смена задания для уже выполнившего его пользователя
            // одновременно отменяет старое выполнение и начисление.
            $this->resetTaskInTransaction($userId, $user);

            $stmt = $this->conn->prepare(
                'INSERT INTO user_task_overrides (user_id, task_date, task_id)
                 VALUES (?, CURDATE(), ?)
                 ON DUPLICATE KEY UPDATE task_id = ?'
            );
            $stmt->bind_param('iii', $userId, $taskId, $taskId);
            $stmt->execute();
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    private function resetTaskInTransaction(int $userId, array $user): bool
    {
        $stmt = $this->conn->prepare(
            'SELECT coins_earned FROM task_completions
             WHERE user_id = ? AND completion_date = CURDATE() FOR UPDATE'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();
        if ($record === null) {
            return false;
        }

        $earned = (int) $record['coins_earned'];
        $this->assertEnoughCoins($user, $earned);

        $stmt = $this->conn->prepare(
            'DELETE FROM task_completions
             WHERE user_id = ? AND completion_date = CURDATE()'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();

        $stmt = $this->conn->prepare(
            'UPDATE users SET coins = coins - ? WHERE id = ?'
        );
        $stmt->bind_param('ii', $earned, $userId);
        $stmt->execute();
        return true;
    }

    private function assertEnoughCoins(array $user, int $amount): void
    {
        if ((int) $user['coins'] < $amount) {
            throw new DomainException(
                'Недостаточно монет для отмены начисления. Данные не изменены.'
            );
        }
    }
}
