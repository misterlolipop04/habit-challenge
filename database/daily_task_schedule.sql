CREATE TABLE IF NOT EXISTS daily_task_schedule (
    task_date DATE PRIMARY KEY,
    task_id INT NOT NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(id)
);

INSERT IGNORE INTO daily_task_schedule (task_date, task_id)
SELECT completion_date, task_id
FROM task_completions
WHERE completion_date = CURDATE()
ORDER BY id DESC
LIMIT 1;