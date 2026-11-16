-- Индивидуальная замена испытания на конкретную дату для конкретного пользователя.
-- Не удаляет и не меняет глобальное расписание daily_task_schedule.
CREATE TABLE IF NOT EXISTS user_task_overrides (
    user_id INT NOT NULL,
    task_date DATE NOT NULL,
    task_id INT NOT NULL,
    PRIMARY KEY (user_id, task_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id)
);
