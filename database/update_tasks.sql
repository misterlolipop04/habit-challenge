UPDATE tasks
SET title = 'Мини-тренировка',
    description = 'Сделайте 10 приседаний'
WHERE id = 1;

UPDATE tasks
SET title = 'Цифровой перерыв',
    description = 'Проведите 15 минут без телефона'
WHERE id = 2;

UPDATE tasks
SET title = 'Немного порядка',
    description = 'Уберите 3 вещи на место'
WHERE id = 3;

INSERT INTO tasks (title, description, coins_reward)
VALUES ('Стакан воды', 'Выпейте стакан воды', 30);