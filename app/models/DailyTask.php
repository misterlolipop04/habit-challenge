<?php

class DailyTask
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getToday($userId = null)
    {
        // Сначала проверяем индивидуальное назначение для пользователя.
        if ($userId !== null) {
            $stmt = $this->conn->prepare(
                'SELECT tasks.*
                 FROM user_task_overrides AS overrides
                 JOIN tasks ON tasks.id = overrides.task_id
                 WHERE overrides.user_id = ? AND overrides.task_date = CURDATE()'
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $task = $stmt->get_result()->fetch_assoc();
            if ($task !== null) {
                return $task;
            }
        }

        // Иначе используем общее задание, закреплённое за датой.
        $result = $this->conn->query(
            'SELECT tasks.*
             FROM daily_task_schedule AS schedule
             JOIN tasks ON tasks.id = schedule.task_id
             WHERE schedule.task_date = CURDATE()'
        );
        $task = $result->fetch_assoc();
        if ($task !== null) {
            return $task;
        }

        // При первом посещении дня создаём общее назначение.
        $result = $this->conn->query('SELECT * FROM tasks ORDER BY id');
        $tasks = $result->fetch_all(MYSQLI_ASSOC);
        if (count($tasks) === 0) {
            return null;
        }

        $result = $this->conn->query(
            'SELECT DAYOFYEAR(CURDATE()) - 1 AS day_number'
        );
        $dayNumber = (int) $result->fetch_assoc()['day_number'];
        $index = $dayNumber % count($tasks);
        $taskId = (int) $tasks[$index]['id'];

        $stmt = $this->conn->prepare(
            'INSERT IGNORE INTO daily_task_schedule (task_date, task_id)
             VALUES (CURDATE(), ?)'
        );
        $stmt->bind_param('i', $taskId);
        $stmt->execute();

        // Даже при одновременном запросе двух пользователей возвращаем
        // фактически сохранённую запись, а не предполагаемую.
        $result = $this->conn->query(
            'SELECT tasks.*
             FROM daily_task_schedule AS schedule
             JOIN tasks ON tasks.id = schedule.task_id
             WHERE schedule.task_date = CURDATE()'
        );
        return $result->fetch_assoc();
    }

    public function wasCompletedToday($userId)
    {
        $stmt = $this->conn->prepare(
            'SELECT id FROM task_completions
             WHERE user_id = ? AND completion_date = CURDATE()'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function complete($userId)
    {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare(
                'SELECT id FROM users WHERE id = ? FOR UPDATE'
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc() === null) {
                throw new RuntimeException('Пользователь не найден');
            }

            if ($this->wasCompletedToday($userId)) {
                $this->conn->rollback();
                return false;
            }

            // Важное отличие от прежнего варианта: учитываем пользователя.
            $task = $this->getToday($userId);
            if ($task === null) {
                throw new RuntimeException('Нет доступного испытания');
            }

            $taskId = (int) $task['id'];
            $reward = (int) $task['coins_reward'];

            $stmt = $this->conn->prepare(
                'INSERT INTO task_completions
                 (user_id, task_id, completion_date, coins_earned)
                 VALUES (?, ?, CURDATE(), ?)'
            );
            $stmt->bind_param('iii', $userId, $taskId, $reward);
            $stmt->execute();

            $stmt = $this->conn->prepare(
                'UPDATE users SET coins = coins + ? WHERE id = ?'
            );
            $stmt->bind_param('ii', $reward, $userId);
            $stmt->execute();

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function getHistory($userId)
    {
        $stmt = $this->conn->prepare(
            'SELECT tasks.title, task_completions.completion_date,
                    task_completions.coins_earned
             FROM task_completions
             JOIN tasks ON tasks.id = task_completions.task_id
             WHERE task_completions.user_id = ?
             ORDER BY task_completions.completion_date DESC
             LIMIT 7'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
