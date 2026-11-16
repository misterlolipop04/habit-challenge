<?php
/** @var bool $isAuthenticated */
function adminEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Админ-панель · Habit Challenge</title>
    <link rel="stylesheet" href="/public/style.css">
    <link rel="stylesheet" href="/public/admin.css">
</head>
<body>
<div class="page-shell admin-shell">
    <header class="site-header">
        <a href="/" class="brand">
            <span class="brand-icon">H</span>
            <span>Habit Challenge</span>
        </a>
        <?php if ($isAuthenticated): ?>
            <form method="POST" action="/admin/">
                <input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="button button-muted">Выйти</button>
            </form>
        <?php endif; ?>
    </header>

    <main class="admin-main">
        <?php if ($isAuthenticated): ?>
            <h1 class="admin-page-title">Админ-панель</h1>
        <?php endif; ?>

        <?php if ($error !== null): ?>
            <div class="admin-alert admin-alert-error" role="alert"><?= adminEscape($error) ?></div>
        <?php endif; ?>
        <?php if ($noticeError !== null): ?>
            <div class="admin-alert admin-alert-error" role="alert"><?= adminEscape($noticeError) ?></div>
        <?php endif; ?>
        <?php if ($notice !== null): ?>
            <div class="admin-alert admin-alert-success" role="status"><?= adminEscape($notice) ?></div>
        <?php endif; ?>

        <?php if (!$isAuthenticated): ?>
            <section class="card admin-login-card">
                <h1>Вход</h1>
                <form method="POST" action="/admin/" class="admin-login-form">
                    <input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>">
                    <input type="hidden" name="action" value="login">
                    <label for="admin-password">Пароль</label>
                    <input id="admin-password" type="password" name="password"
                           required autocomplete="current-password" autofocus>
                    <button type="submit" class="button">Войти</button>
                </form>
            </section>
        <?php elseif ($status === null): ?>
            <section class="card admin-card"><p class="admin-empty">Нет доступных пользователей.</p></section>
        <?php else: ?>
            <section class="card admin-user-card">
                <h2>Пользователь</h2>
                <form method="GET" action="/admin/" class="admin-user-form">
                    <label for="user_id" class="admin-sr-only">Выбор пользователя</label>
                    <select name="user_id" id="user_id">
                        <?php foreach ($users as $account): ?>
                            <option value="<?= (int) $account['id'] ?>"
                                <?= (int) $account['id'] === $selectedUserId ? 'selected' : '' ?>>
                                #<?= (int) $account['id'] ?> — <?= adminEscape($account['challenge_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button">Выбрать</button>
                </form>
            </section>

            <div class="admin-stats-grid">
                <div class="card admin-stat">
                    <span class="stat-label">Монеты</span>
                    <strong><?= (int) $status['coins'] ?></strong>
                </div>
                <div class="card admin-stat">
                    <span class="stat-label">Дни активности</span>
                    <strong><?= (int) $status['current_streak'] ?></strong>
                </div>
                <div class="card admin-stat">
                    <span class="stat-label">Рекорд активности</span>
                    <strong><?= (int) $status['best_streak'] ?></strong>
                </div>
            </div>

            <?php if ($selectedChallenge): ?>
                <?php $previewValue = (int) ($selectedChallenge['preview_streak'] ?? $selectedChallenge['real_display_streak']); ?>
                <section class="card admin-card admin-preview-card">
                    <div class="admin-card-top">
                        <h3>Челлендж</h3>
                        <span class="admin-task-source"><?= $selectedChallenge['preview_streak'] !== null ? 'Тест' : 'Реальный' ?></span>
                    </div>
                    <form method="GET" action="/admin/" class="admin-preview-select">
                        <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                        <label for="admin-challenge" class="admin-sr-only">Челлендж</label>
                        <select id="admin-challenge" name="challenge_id">
                            <?php foreach ($challenges as $challenge): ?>
                                <option value="<?= (int) $challenge['id'] ?>"
                                    <?= (int) $challenge['id'] === $selectedChallengeId ? 'selected' : '' ?>>
                                    <?= adminEscape($challenge['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button button-muted">Выбрать</button>
                    </form>
                    <div class="admin-checkin-row">
                        <span class="status <?= (int) $selectedChallenge['done_today'] === 1 ? 'status-done' : 'status-active' ?>">
                            <?= (int) $selectedChallenge['done_today'] === 1 ? 'Отмечен сегодня' : 'Не отмечен' ?>
                        </span>
                        <form method="POST" action="/admin/">
                            <input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>">
                            <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                            <input type="hidden" name="challenge_id" value="<?= (int) $selectedChallengeId ?>">
                            <input type="hidden" name="action" value="reset_checkin">
                            <button class="button button-muted" type="submit"
                                <?= (int) $selectedChallenge['done_today'] !== 1 ? 'disabled' : '' ?>>Сбросить отметку</button>
                        </form>
                    </div>
                    <h4 class="admin-subheading">Рамка стрика</h4>
                    <div class="admin-preview-value">
                        <strong><?= $previewValue ?></strong>
                        <span>дней · фактически <?= (int) $selectedChallenge['real_display_streak'] ?></span>
                    </div>
                    <form method="POST" action="/admin/" class="admin-preview-form">
                        <input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>">
                        <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                        <input type="hidden" name="challenge_id" value="<?= (int) $selectedChallengeId ?>">
                        <input type="hidden" name="action" value="preview_streak">
                        <label for="streak_value" class="admin-sr-only">Тестовый стрик</label>
                        <input id="streak_value" type="number" name="streak_value" min="0" max="3650" value="<?= $previewValue ?>" required>
                        <button type="submit" class="button">Применить</button>
                    </form>
                    <div class="admin-preview-buttons">
                        <?php foreach ([1, 7] as $delta): ?>
                            <form method="POST" action="/admin/">
                                <input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                                <input type="hidden" name="challenge_id" value="<?= (int) $selectedChallengeId ?>">
                                <input type="hidden" name="action" value="add_streak">
                                <input type="hidden" name="delta" value="<?= $delta ?>">
                                <button class="button button-muted" type="submit">+<?= $delta ?></button>
                            </form>
                        <?php endforeach; ?>
                        <?php foreach ([7,14,30,60,100] as $tierValue): ?>
                            <form method="POST" action="/admin/">
                                <input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                                <input type="hidden" name="challenge_id" value="<?= (int) $selectedChallengeId ?>">
                                <input type="hidden" name="action" value="preview_streak">
                                <input type="hidden" name="streak_value" value="<?= $tierValue ?>">
                                <button class="button button-muted" type="submit"><?= $tierValue ?></button>
                            </form>
                        <?php endforeach; ?>
                        <?php if ($selectedChallenge['preview_streak'] !== null): ?>
                            <form method="POST" action="/admin/">
                                <input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                                <input type="hidden" name="challenge_id" value="<?= (int) $selectedChallengeId ?>">
                                <input type="hidden" name="action" value="clear_streak_preview">
                                <button class="button button-muted" type="submit">Сбросить тест</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <div class="admin-section-title">
                <h2>Сегодня</h2>
                <span><?= adminEscape($status['today']) ?></span>
            </div>

            <div class="admin-actions-grid">
                <section class="card admin-card admin-status-card">
                    <div class="admin-card-top">
                        <h3>Отметки за сегодня</h3>
                        <span class="status <?= (int) $status['checkins_today'] > 0 ? 'status-done' : 'status-active' ?>">
                            <?= (int) $status['checkins_today'] ?> / <?= count($challenges) ?>
                        </span>
                    </div>
                    <form method="POST" action="/admin/">
                        <input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>">
                        <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                        <input type="hidden" name="challenge_id" value="<?= (int) $selectedChallengeId ?>">
                        <input type="hidden" name="action" value="reset_all_checkins">
                        <button type="submit" class="button button-muted"
                            <?= (int) $status['checkins_today'] === 0 && (int) $status['reward_done'] === 0 ? 'disabled' : '' ?>>
                            Сбросить все отметки
                        </button>
                    </form>
                </section>
                <section class="card admin-card admin-status-card">
                    <div class="admin-card-top">
                        <h3>Ежедневное испытание</h3>
                        <span class="status <?= (int) $status['task_done'] > 0 ? 'status-done' : 'status-active' ?>">
                            <?= (int) $status['task_done'] > 0 ? 'Выполнено' : 'Не выполнено' ?>
                        </span>
                    </div>
                    <form method="POST" action="/admin/">
                        <input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>">
                        <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                        <input type="hidden" name="challenge_id" value="<?= (int) $selectedChallengeId ?>">
                        <input type="hidden" name="action" value="reset_task">
                        <button type="submit" class="button button-muted"
                            <?= (int) $status['task_done'] === 0 ? 'disabled' : '' ?>>
                            Сбросить испытание
                        </button>
                    </form>
                </section>
            </div>

            <section class="card admin-reset-card">
                <h3>Полный сброс за сегодня</h3>
                <form method="POST" action="/admin/">
                    <input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                    <input type="hidden" name="challenge_id" value="<?= (int) $selectedChallengeId ?>">
                    <input type="hidden" name="action" value="reset_both">
                    <button type="submit" class="button button-muted"
                        <?= (int) $status['reward_done'] === 0 && (int) $status['checkins_today'] === 0 && (int) $status['task_done'] === 0 ? 'disabled' : '' ?>>
                        Сбросить всё
                    </button>
                </form>
            </section>

            <?php $currentTaskId = $status['override_task_id'] ?? $status['global_task_id']; ?>
            <?php
            $currentTitle = 'Не назначено';
            if ($currentTaskId !== null) {
                $currentTitle = 'ID ' . $currentTaskId;
                foreach ($tasks as $task) {
                    if ((int) $task['id'] === (int) $currentTaskId) {
                        $currentTitle = $task['title'];
                        break;
                    }
                }
            }
            ?>
            <section class="card admin-card admin-task-card">
                <div class="admin-card-top">
                    <h3>Смена испытания</h3>
                    <span class="admin-task-source">
                        <?= $status['override_task_id'] !== null ? 'Индивидуальное' : 'По расписанию' ?>
                    </span>
                </div>
                <div class="admin-current-task">
                    <span>Текущее испытание</span>
                    <strong><?= adminEscape($currentTitle) ?></strong>
                </div>
                <form method="POST" action="/admin/" class="admin-task-form">
                    <input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                    <input type="hidden" name="challenge_id" value="<?= (int) $selectedChallengeId ?>">
                    <input type="hidden" name="action" value="change_task">
                    <label for="task_id" class="admin-sr-only">Новое испытание</label>
                    <select name="task_id" id="task_id" required>
                        <?php foreach ($tasks as $task): ?>
                            <option value="<?= (int) $task['id'] ?>"
                                <?= (int) $task['id'] === (int) $currentTaskId ? 'selected' : '' ?>>
                                <?= adminEscape($task['title']) ?> (+<?= (int) $task['coins_reward'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button" <?= empty($tasks) ? 'disabled' : '' ?>>Назначить</button>
                </form>
                <?php if ((int) $status['task_done'] > 0): ?>
                    <p class="admin-warning">Назначение другого испытания отменит сегодняшнее выполнение и начисленные за него монеты.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
