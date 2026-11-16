<?php

class DailyReward
{
    private $conn;
    
    public function __construct($conn)
    {
        $this->conn = $conn;
    }
    
    public function wasClaimedToday($userId)
    {
        $stmt = $this->conn->prepare(
            'SELECT id FROM reward_history
             WHERE user_id = ? AND reward_date = CURDATE()'
            );
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    public function claim($userId)
    {
        $this->conn->begin_transaction();
        
        try {
            $stmt = $this->conn->prepare(
                'SELECT current_streak, last_claim_date,
                    CURDATE() AS today,
                    DATE_SUB(CURDATE(), INTERVAL 1 DAY) AS yesterday
             FROM users WHERE id = ? FOR UPDATE'
                );
            
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            if ($user === null) {
                throw new RuntimeException('Пользователь не найден');
            }
            
            if ($user['last_claim_date'] === $user['today']) {
                $this->conn->rollback();
                return false;
            }
            
            $newStreak = $user['last_claim_date'] === $user['yesterday']
            ? (int) $user['current_streak'] + 1
            : 1;
            
            $reward = 10;
            
            if ($newStreak % 7 === 0) {
                $reward += 50;
            }
            
            $stmt = $this->conn->prepare(
                'UPDATE users
             SET coins = coins + ?,
                 current_streak = ?,
                 best_streak = GREATEST(best_streak, ?),
                 last_claim_date = CURDATE()
             WHERE id = ?'
                );
            
            $stmt->bind_param('iiii', $reward, $newStreak, $newStreak, $userId);
            $stmt->execute();
            
            $stmt = $this->conn->prepare(
                'INSERT INTO reward_history (user_id, reward_date, coins_earned)
             VALUES (?, CURDATE(), ?)'
                );
            
            $stmt->bind_param('ii', $userId, $reward);
            $stmt->execute();
            
            $this->conn->commit();
            
            return $reward;
            
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    public function getHistory($userId)
    {
        $stmt = $this->conn->prepare(
            'SELECT reward_date, coins_earned
         FROM reward_history
         WHERE user_id = ?
         ORDER BY reward_date DESC
         LIMIT 7'
            );
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}