<?php
function dayWord($count)
{
    if ($count % 100 >= 11 && $count % 100 <= 14) {
        return 'дней';
    }
    if ($count % 10 === 1) {
        return 'день';
    }
    if ($count % 10 >= 2 && $count % 10 <= 4) {
        return 'дня';
    }
    return 'дней';
}

// Рамка относится к выбранному челленджу. Тестовый стрик влияет только на вид.
$streak = $activeChallenge
    ? (int) ($activeChallenge['preview_streak'] ?? $activeChallenge['real_display_streak']) : 0;
$bestStreak = $activeChallenge
    ? (int) $activeChallenge['best_streak'] : 0;
$challengeDone = $activeChallenge && (int) $activeChallenge['done_today'] === 1;
if ($streak >= 100) {
    $streakTier = 'legend';
} elseif ($streak >= 60) {
    $streakTier = 'amethyst';
} elseif ($streak >= 30) {
    $streakTier = 'gold';
} elseif ($streak >= 14) {
    $streakTier = 'silver';
} elseif ($streak >= 7) {
    $streakTier = 'bronze';
} else {
    $streakTier = 'base';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habit Challenge</title>
    <link rel="stylesheet" href="/public/style.css">
</head>
<body>
<div class="page-shell">
    <header class="site-header">
        <a href="/" class="brand"><span class="brand-icon">H</span><span>Habit Challenge</span></a>
        <nav class="navigation" aria-label="Основная навигация">
            <a href="/" aria-current="page">Главная</a>
            <a href="/history/">История</a>
        </nav>
    </header>
    <main>
        <section class="welcome">
            <span class="eyebrow">ВАШ ПРОГРЕСС</span>
            <h1><?= htmlspecialchars($activeChallenge['title'] ?? 'Мои челленджи', ENT_QUOTES, 'UTF-8') ?></h1>
        </section>
        <?php if ($noticeError): ?>
            <p class="site-error" role="alert"><?= htmlspecialchars($noticeError, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <details id="challenges" class="challenge-drawer">
            <summary class="challenge-drawer-toggle">
                <span class="challenge-drawer-title">Сменить челлендж</span>
                <span class="challenge-drawer-meta"><?= count($challenges) ?> / 20 <span class="challenge-chevron" aria-hidden="true">⌄</span></span>
            </summary>
            <div class="challenge-drawer-body">
                <div class="challenge-list">
                    <?php foreach ($challenges as $challenge): ?>
                        <?php
                            $displayStreak = (int) ($challenge['preview_streak'] ?? $challenge['real_display_streak']);
                            $isActive = (int) $challenge['is_active'] === 1;
                        ?>
                        <form action="/" method="POST" class="challenge-tab-form">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="select_challenge">
                            <input type="hidden" name="challenge_id" value="<?= (int) $challenge['id'] ?>">
                            <button type="submit" class="challenge-tab <?= $isActive ? 'challenge-tab-active' : '' ?>"
                                    <?= $isActive ? 'aria-current="true"' : '' ?>>
                                <strong><?= htmlspecialchars($challenge['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= $displayStreak ?> <?= dayWord($displayStreak) ?><?= (int) $challenge['done_today'] ? ' · ✓ Сегодня' : '' ?></span>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
                <?php if (count($challenges) < 20): ?>
                    <form class="challenge-create" action="/" method="POST">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="create_challenge">
                        <label class="sr-only" for="new-challenge">Новый челлендж</label>
                        <input id="new-challenge" type="text" name="challenge_name" maxlength="50" placeholder="Новый челлендж" required>
                        <button class="button" type="submit">Создать</button>
                    </form>
                <?php endif; ?>
            </div>
        </details>
        <section class="stats-grid">
            <article class="stat-card">
                <span class="stat-label">Баланс монет</span>
                <div class="stat-value"><?= (int) $user['coins'] ?></div>
                <span class="stat-caption">Заработано монет</span>
            </article>
            <article class="stat-card streak-card streak-tier-<?= $streakTier ?>">
                <span class="stat-label">Текущий стрик</span>
                <div class="stat-value"><?= $streak ?></div>
                <span class="stat-caption"><?= dayWord($streak) ?> подряд<?= $activeChallenge && $activeChallenge['preview_streak'] !== null ? ' · тест' : '' ?></span>
            </article>
            <article class="stat-card">
                <span class="stat-label">Личный рекорд</span>
                <div class="stat-value"><?= $bestStreak ?></div>
                <span class="stat-caption"><?= dayWord($bestStreak) ?></span>
            </article>
        </section>
        <section id="today" class="content-section">
            <div class="section-heading">
                <div><span class="eyebrow">ВАШИ ПРИВЫЧКИ</span><h2>Сегодня</h2></div>
            </div>
            <div class="activity-grid">
                <article class="card activity-card">
                    <div class="card-top">
                        <span class="eyebrow">ЕЖЕДНЕВНАЯ ОТМЕТКА</span>
                        <span class="status <?= $challengeDone ? 'status-done' : 'status-active' ?>">
                            <?= $challengeDone ? 'Выполнено' : 'Сегодня' ?>
                        </span>
                    </div>
                    <div class="activity-content">
                        <div class="activity-icon">✦</div>
                        <h3><?= htmlspecialchars($activeChallenge['title'] ?? 'Мой челлендж', ENT_QUOTES, 'UTF-8') ?></h3>
                        <p><?= $challengeDone ? 'Сегодняшняя отметка сделана.' : 'Выполнили привычку сегодня?' ?></p>
                        <p class="bonus-note">Каждый 7-й день активности — дополнительные 50 монет</p>
                    </div>
                    <div class="activity-footer">
                        <div class="reward-amount">
                            <?php if (!$claimedToday): ?>
                                <strong>+10</strong><span>монет</span>
                            <?php else: ?>
                                <span class="reward-used">Монеты за сегодня начислены</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($challengeDone): ?>
                            <div class="completed-message"><span>✓</span>Отмечено</div>
                        <?php elseif ($activeChallenge): ?>
                            <form action="/" method="POST">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="complete_challenge">
                                <input type="hidden" name="challenge_id" value="<?= (int) $activeChallenge['id'] ?>">
                                <button type="submit" class="button">Отметить</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
                <article class="card activity-card">
                    <div class="card-top">
                        <span class="eyebrow">ЕЖЕДНЕВНОЕ ИСПЫТАНИЕ</span>
                        <?php if ($completedToday): ?>
                            <span class="status status-done">Выполнено</span>
                        <?php else: ?>
                            <span class="status status-active">Сегодня</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($todayTask === null): ?>
                        <div class="activity-content">
                            <div class="activity-icon">◎</div>
                            <h3>Испытаний пока нет</h3>
                            <p>Возвращайтесь позже.</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-content">
                            <div class="activity-icon">◎</div>
                            <h3><?= htmlspecialchars($todayTask['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= htmlspecialchars($todayTask['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="activity-footer">
                            <div class="reward-amount"><strong>+<?= (int) $todayTask['coins_reward'] ?></strong><span>монет</span></div>
                            <?php if ($completedToday): ?>
                                <div class="completed-message"><span>✓</span>Испытание выполнено</div>
                            <?php else: ?>
                                <form method="POST" action="/">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="complete_task">
                                    <button class="button" type="submit">Выполнил!</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </div>
        </section>
        <section id="settings" class="content-section">
            <div class="section-heading">
                <div><span class="eyebrow">ПЕРСОНАЛИЗАЦИЯ</span><h2>Настройки</h2></div>
            </div>
            <div class="card settings-card">
                <div class="settings-description">
                    <h3>Переименовать челлендж</h3>
                </div>
                <form method="POST" action="/" class="settings-form">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="update_challenge_name">
                    <input type="text" name="challenge_name" aria-label="Название челленджа" value="<?= htmlspecialchars($activeChallenge['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="50" required>
                    <button class="button" type="submit">Сохранить</button>
                </form>
            </div>
        </section>
    </main>
    <footer class="site-footer">Habit Challenge · Маленькие шаги каждый день</footer>
</div>
</body>
</html>
