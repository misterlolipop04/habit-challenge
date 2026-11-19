CREATE DATABASE IF NOT EXISTS daily_rewards;

USE daily_rewards;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_name VARCHAR(100) NOT NULL DEFAULT 'Мой челлендж',
    coins INT NOT NULL DEFAULT 0,
    current_streak INT NOT NULL DEFAULT 0,
    best_streak INT NOT NULL DEFAULT 0,
    last_claim_date DATE DEFAULT NULL,
    active_challenge_id INT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS reward_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reward_date DATE NOT NULL,
    coins_earned INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE (user_id, reward_date)
);

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    coins_reward INT NOT NULL DEFAULT 30
);

CREATE TABLE IF NOT EXISTS task_completions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_id INT NOT NULL,
    completion_date DATE NOT NULL,
    coins_earned INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (task_id) REFERENCES tasks(id),
    UNIQUE (user_id, completion_date)
);

CREATE TABLE IF NOT EXISTS daily_task_schedule (
    task_date DATE PRIMARY KEY,
    task_id INT NOT NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(id)
);


CREATE TABLE IF NOT EXISTS user_task_overrides (
    user_id INT NOT NULL,
    task_date DATE NOT NULL,
    task_id INT NOT NULL,
    PRIMARY KEY (user_id, task_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id)
);


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

-- Данные для демонстрации.
INSERT IGNORE INTO users (id, challenge_name)
VALUES (1, 'Мой челлендж');

INSERT INTO challenges (user_id, title)
SELECT u.id, u.challenge_name
FROM users u
WHERE u.id = 1
  AND NOT EXISTS (SELECT 1 FROM challenges c WHERE c.user_id = u.id);

UPDATE users u
JOIN (SELECT user_id, MIN(id) AS first_id FROM challenges GROUP BY user_id) c
    ON c.user_id = u.id
SET u.active_challenge_id = c.first_id
WHERE u.id = 1 AND u.active_challenge_id IS NULL;

INSERT INTO tasks (title, description, coins_reward)
VALUES
    ('Мини-тренировка', 'Сделайте 10 приседаний', 30),
    ('Цифровой перерыв', 'Проведите 15 минут без телефона', 30),
    ('Немного порядка', 'Уберите 3 вещи на место', 30),
    ('Стакан воды', 'Выпейте стакан воды', 30);
