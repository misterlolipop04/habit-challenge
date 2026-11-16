-- Habit Challenge: несколько независимых привычек на пользователя.
-- Можно выполнять повторно: данные и история наград не удаляются.
CREATE TABLE IF NOT EXISTS challenges (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(50) NOT NULL,
    current_streak INT NOT NULL DEFAULT 0,
    best_streak INT NOT NULL DEFAULT 0,
    last_completed_date DATE DEFAULT NULL,
    preview_streak INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_challenges_user (user_id),
    CONSTRAINT fk_challenges_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS challenge_checkins (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT NOT NULL,
    completion_date DATE NOT NULL,
    UNIQUE KEY uniq_challenge_day (challenge_id, completion_date),
    CONSTRAINT fk_checkins_challenge FOREIGN KEY (challenge_id)
        REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- У старой таблицы users нет выбранного челленджа.
-- Проверка через information_schema позволяет повторно запускать миграцию.
SET @has_active_challenge = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'active_challenge_id'
);
SET @add_active_challenge = IF(@has_active_challenge = 0,
    'ALTER TABLE users ADD COLUMN active_challenge_id INT NULL', 'SELECT 1');
PREPARE mig FROM @add_active_challenge;
EXECUTE mig;
DEALLOCATE PREPARE mig;

-- Существующее название становится первым челленджем каждого пользователя.
-- Стрик прежних наград остаётся в users и НЕ превращается в фиктивные
-- отметки привычек: у нового счётчика привычки честный старт с нуля.
INSERT INTO challenges (user_id, title)
SELECT u.id, COALESCE(NULLIF(TRIM(u.challenge_name), ''), 'Мой челлендж')
FROM users u
WHERE NOT EXISTS (SELECT 1 FROM challenges c WHERE c.user_id = u.id);

UPDATE users u
JOIN (SELECT user_id, MIN(id) AS first_id FROM challenges GROUP BY user_id) c
    ON c.user_id = u.id
SET u.active_challenge_id = c.first_id
WHERE u.active_challenge_id IS NULL;
