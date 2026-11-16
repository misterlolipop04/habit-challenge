<?php

declare(strict_types=1);

/**
 * Полная история активности пользователя.
 * Монеты принадлежат аккаунту, поэтому не приписываются отдельному
 * челленджу: первая отметка дня могла быть сделана в любом из них.
 */
class ActivityHistory
{
    public function __construct(private mysqli $conn)
    {
    }

    public function getForUser(int $userId): array
    {
        $days = [];

        $stmt = $this->conn->prepare(
            'SELECT ci.id, ci.completion_date AS activity_date,
                    c.id AS challenge_id, c.title
             FROM challenge_checkins ci
             JOIN challenges c ON c.id = ci.challenge_id
             WHERE c.user_id = ?
             ORDER BY ci.completion_date DESC, ci.id ASC'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $entry) {
            $date = $entry['activity_date'];
            $this->ensureDay($days, $date);
            $days[$date]['entries'][] = [
                'type' => 'checkin',
                'challenge_id' => (int) $entry['challenge_id'],
                'title' => $entry['title'],
                'coins' => 0,
                'order' => (int) $entry['id'],
            ];
        }

        $stmt = $this->conn->prepare(
            'SELECT tc.id, tc.completion_date AS activity_date,
                    tc.coins_earned, t.title
             FROM task_completions tc
             JOIN tasks t ON t.id = tc.task_id
             WHERE tc.user_id = ?
             ORDER BY tc.completion_date DESC, tc.id DESC'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $entry) {
            $date = $entry['activity_date'];
            $this->ensureDay($days, $date);
            $coins = (int) $entry['coins_earned'];
            $days[$date]['total_coins'] += $coins;
            $days[$date]['entries'][] = [
                'type' => 'task',
                'challenge_id' => null,
                'title' => $entry['title'],
                'coins' => $coins,
                'order' => (int) $entry['id'],
            ];
        }

        $stmt = $this->conn->prepare(
            'SELECT reward_date AS activity_date, coins_earned
             FROM reward_history
             WHERE user_id = ?
             ORDER BY reward_date DESC'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $entry) {
            $date = $entry['activity_date'];
            $this->ensureDay($days, $date);
            $coins = (int) $entry['coins_earned'];
            $days[$date]['total_coins'] += $coins;
            $days[$date]['reward_coins'] += $coins;
        }

        krsort($days);
        foreach ($days as &$day) {
            // Проверка привычек -> испытания; точный порядок во времени
            // не сохраняется в текущей схеме БД и не имитируется.
            usort($day['entries'], static function (array $a, array $b): int {
                $rank = ['checkin' => 0, 'task' => 1];
                return ($rank[$a['type']] <=> $rank[$b['type']])
                    ?: ($a['order'] <=> $b['order']);
            });
        }
        unset($day);

        return array_values($days);
    }

    private function ensureDay(array &$days, string $date): void
    {
        if (!isset($days[$date])) {
            $days[$date] = [
                'date' => $date,
                'total_coins' => 0,
                'reward_coins' => 0,
                'entries' => [],
            ];
        }
    }
}
