CREATE DATABASE IF NOT EXISTS daily_rewards;

USE daily_rewards;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_name VARCHAR(100) NOT NULL DEFAULT 'Мой челлендж',
    coins INT NOT NULL DEFAULT 0,
    current_streak INT NOT NULL DEFAULT 0,
    best_streak INT NOT NULL DEFAULT 0,
    last_claim_date DATE DEFAULT NULL
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

INSERT INTO tasks (title, description, coins_reward)
VALUES
    ('Мини-тренировка', 'Сделайте 10 приседаний', 30),
    ('Цифровой перерыв', 'Проведите 15 минут без телефона', 30),
    ('Немного порядка', 'Уберите 3 вещи на место', 30),
    ('Стакан воды', 'Выпейте стакан воды', 30);