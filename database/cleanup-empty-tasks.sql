-- Одноразовая очистка устаревших пустых испытаний.
-- Идемпотентно: повторный запуск не изменяет уже исправленные задания.
-- Выполненные испытания и заработанные монеты НЕ удаляются.
START TRANSACTION;

-- Выбираем существующее корректное испытание для замены некорректных назначений.
SET @valid_task_id = (
    SELECT MAX(id) FROM tasks
    WHERE TRIM(title) <> '' AND TRIM(description) <> ''
);

-- Переназначаем только пустые задания в общем расписании и личных назначениях.
UPDATE daily_task_schedule AS schedule
JOIN tasks AS assigned ON assigned.id = schedule.task_id
SET schedule.task_id = @valid_task_id
WHERE @valid_task_id IS NOT NULL
  AND (TRIM(assigned.title) = '' OR TRIM(assigned.description) = '');

UPDATE user_task_overrides AS overrides
JOIN tasks AS assigned ON assigned.id = overrides.task_id
SET overrides.task_id = @valid_task_id
WHERE @valid_task_id IS NOT NULL
  AND (TRIM(assigned.title) = '' OR TRIM(assigned.description) = '');

-- Удаляем пустые испытания только если на них НЕ ссылаются результаты
-- выполнения, расписание или персональные назначения.
DELETE FROM tasks
WHERE (TRIM(title) = '' OR TRIM(description) = '')
  AND NOT EXISTS (
      SELECT 1 FROM task_completions WHERE task_completions.task_id = tasks.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM daily_task_schedule WHERE daily_task_schedule.task_id = tasks.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM user_task_overrides WHERE user_task_overrides.task_id = tasks.id
  );

COMMIT;
