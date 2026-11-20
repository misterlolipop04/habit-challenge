<?php

declare(strict_types=1);

require_once __DIR__ . '/StreakRewardRules.php';

class Challenge
{
    private const MAX_CHALLENGES = 20;

    public function __construct(private mysqli $conn)
    {
    }

    private function normalizeTitle(string $title): string
    {
        $title = trim($title);
        if (!preg_match('/\A.{1,50}\z/uD', $title) || preg_match('/[\x00-\x1f\x7f]/', $title)) {
            throw new DomainException('Введите название от 1 до 50 символов.');
        }
        return $title;
    }

    public function listForUser(int $userId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT c.id, c.title, c.current_streak, c.best_streak,
                    c.preview_streak, c.last_completed_date,
                    (c.last_completed_date = CURDATE()) AS done_today,
                    CASE WHEN c.last_completed_date >= CURDATE() - INTERVAL 1 DAY
                         THEN c.current_streak ELSE 0 END AS real_display_streak,
                    (u.active_challenge_id = c.id) AS is_active
             FROM challenges c JOIN users u ON u.id = c.user_id
             WHERE c.user_id = ? ORDER BY c.created_at, c.id'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getActive(int $userId): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT c.id, c.title, c.current_streak, c.best_streak,
                    c.last_completed_date, c.preview_streak,
                    (c.last_completed_date = CURDATE()) AS done_today,
                    CASE WHEN c.last_completed_date >= CURDATE() - INTERVAL 1 DAY
                         THEN c.current_streak ELSE 0 END AS real_display_streak
             FROM users u JOIN challenges c ON c.id = u.active_challenge_id
                   AND c.user_id = u.id WHERE u.id = ?'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function create(int $userId, string $title): int
    {
        $title = $this->normalizeTitle($title);
        $this->conn->begin_transaction();
        try {
            $this->lockUser($userId);
            $stmt = $this->conn->prepare('SELECT COUNT(*) AS count FROM challenges WHERE user_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            if ((int) $stmt->get_result()->fetch_assoc()['count'] >= self::MAX_CHALLENGES) {
                throw new DomainException('Можно создать не более 20 челленджей.');
            }
            $stmt = $this->conn->prepare('INSERT INTO challenges (user_id, title) VALUES (?, ?)');
            $stmt->bind_param('is', $userId, $title);
            $stmt->execute();
            $challengeId = (int) $this->conn->insert_id;
            $this->setActiveChallenge($userId, $challengeId, $title);
            $this->conn->commit();
            return $challengeId;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function select(int $userId, int $challengeId): void
    {
        $this->conn->begin_transaction();
        try {
            $this->lockUser($userId);
            $stmt = $this->conn->prepare('SELECT title FROM challenges WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $challengeId, $userId);
            $stmt->execute();
            $challenge = $stmt->get_result()->fetch_assoc();
            if ($challenge === null) {
                throw new DomainException('Челлендж не найден.');
            }
            $title = $challenge['title'];
            $this->setActiveChallenge($userId, $challengeId, $title);
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function renameActive(int $userId, string $title): void
    {
        $title = $this->normalizeTitle($title);
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare('SELECT active_challenge_id FROM users WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user === null || !$user['active_challenge_id']) {
                throw new DomainException('Челлендж не найден.');
            }
            $challengeId = (int) $user['active_challenge_id'];
            $stmt = $this->conn->prepare('UPDATE challenges SET title = ? WHERE id = ? AND user_id = ?');
            $stmt->bind_param('sii', $title, $challengeId, $userId);
            $stmt->execute();
            $stmt = $this->conn->prepare('UPDATE users SET challenge_name = ? WHERE id = ?');
            $stmt->bind_param('si', $title, $userId);
            $stmt->execute();
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function complete(int $userId, int $challengeId): bool
    {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare(
                'SELECT id, active_challenge_id, current_streak, best_streak, last_claim_date
                 FROM users WHERE id = ? FOR UPDATE'
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user === null) {
                throw new DomainException('Пользователь не найден.');
            }
            if ((int) $user['active_challenge_id'] !== $challengeId) {
                throw new DomainException('Сначала выберите этот челлендж.');
            }

            $stmt = $this->conn->prepare(
                'SELECT current_streak, best_streak, last_completed_date
                 FROM challenges WHERE id = ? AND user_id = ? FOR UPDATE'
            );
            $stmt->bind_param('ii', $challengeId, $userId);
            $stmt->execute();
            $challenge = $stmt->get_result()->fetch_assoc();
            if ($challenge === null) {
                throw new DomainException('Челлендж не найден.');
            }
            $dates = $this->conn->query(
                'SELECT CURDATE() AS today, CURDATE() - INTERVAL 1 DAY AS yesterday'
            )->fetch_assoc();
            if ($challenge['last_completed_date'] === $dates['today']) {
                $this->conn->rollback();
                return false;
            }

            $newStreak = StreakRewardRules::nextStreak(
                $challenge['last_completed_date'], $dates['yesterday'], (int) $challenge['current_streak']
            );
            $newBest = max((int) $challenge['best_streak'], $newStreak);

            $stmt = $this->conn->prepare(
                'INSERT INTO challenge_checkins (challenge_id, completion_date)
                 VALUES (?, CURDATE())'
            );
            $stmt->bind_param('i', $challengeId);
            $stmt->execute();
            $stmt = $this->conn->prepare(
                'UPDATE challenges SET current_streak = ?, best_streak = ?,
                        last_completed_date = CURDATE(), preview_streak = NULL
                 WHERE id = ? AND user_id = ?'
            );
            $stmt->bind_param('iiii', $newStreak, $newBest, $challengeId, $userId);
            $stmt->execute();

            $stmt = $this->conn->prepare(
                'SELECT id FROM reward_history
                 WHERE user_id = ? AND reward_date = CURDATE() FOR UPDATE'
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc() === null) {
                $rewardStreak = StreakRewardRules::nextStreak(
                    $user['last_claim_date'], $dates['yesterday'], (int) $user['current_streak']
                );
                $rewardBest = max((int) $user['best_streak'], $rewardStreak);
                $coinsEarned = StreakRewardRules::rewardCoins($rewardStreak);

                $stmt = $this->conn->prepare(
                    'INSERT INTO reward_history (user_id, reward_date, coins_earned)
                     VALUES (?, CURDATE(), ?)'
                );
                $stmt->bind_param('ii', $userId, $coinsEarned);
                $stmt->execute();
                $stmt = $this->conn->prepare(
                    'UPDATE users SET coins = coins + ?, current_streak = ?,
                            best_streak = ?, last_claim_date = CURDATE()
                     WHERE id = ?'
                );
                $stmt->bind_param('iiii', $coinsEarned, $rewardStreak, $rewardBest, $userId);
                $stmt->execute();
            }

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    private function lockUser(int $userId): void
    {
        $stmt = $this->conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc() === null) {
            throw new DomainException('Пользователь не найден.');
        }
    }

    private function setActiveChallenge(int $userId, int $challengeId, string $title): void
    {
        $stmt = $this->conn->prepare(
            'UPDATE users SET active_challenge_id = ?, challenge_name = ? WHERE id = ?'
        );
        $stmt->bind_param('isi', $challengeId, $title, $userId);
        $stmt->execute();
    }

    public function getHistory(int $challengeId, int $userId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT ci.completion_date FROM challenge_checkins ci
             JOIN challenges c ON c.id = ci.challenge_id
             WHERE c.user_id = ? AND ci.challenge_id = ?
             ORDER BY ci.completion_date DESC LIMIT 7'
        );
        $stmt->bind_param('ii', $userId, $challengeId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
