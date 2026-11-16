<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История — Habit Challenge</title>
    <link rel="stylesheet" href="/public/style.css">
</head>
<body>
<div class="page-shell">
    <header class="site-header">
        <a href="/" class="brand"><span class="brand-icon">H</span><span>Habit Challenge</span></a>
        <nav class="navigation" aria-label="Основная навигация">
            <a href="/">Главная</a>
            <a href="/history/" aria-current="page">История</a>
        </nav>
    </header>
    <main>
        <section class="welcome history-page-intro">
            <span class="eyebrow">ВАША АКТИВНОСТЬ</span>
            <h1>История</h1>
        </section>
        <section class="content-section history-page-section">
            <div class="history-page-body">
                    <?php if (empty($activityHistory)): ?>
                        <p class="empty-state">Пока нет активности.</p>
                    <?php else: ?>
                        <div class="history-filters" role="group" aria-label="Фильтр истории">
                            <button class="history-filter is-selected" type="button" data-history-filter="all" aria-pressed="true">Всё</button>
                            <?php foreach ($challenges as $challenge): ?>
                                <button class="history-filter" type="button"
                                        data-history-filter="<?= (int) $challenge['id'] ?>" aria-pressed="false">
                                    <?= htmlspecialchars($challenge['title'], ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="history-days">
                            <?php
                                $historyMonthNames = [
                                    1 => 'января', 2 => 'февраля', 3 => 'марта',
                                    4 => 'апреля', 5 => 'мая', 6 => 'июня',
                                    7 => 'июля', 8 => 'августа', 9 => 'сентября',
                                    10 => 'октября', 11 => 'ноября', 12 => 'декабря'
                                ];
                                $historyCurrentYear = (int) date('Y');
                            ?>
                            <?php foreach ($activityHistory as $day): ?>
                                <?php
                                    $historyDate = new DateTimeImmutable($day['date']);
                                    $historyDateTitle = (int) $historyDate->format('j') . ' '
                                        . $historyMonthNames[(int) $historyDate->format('n')];
                                    if ((int) $historyDate->format('Y') !== $historyCurrentYear) {
                                        $historyDateTitle .= ' ' . $historyDate->format('Y');
                                    }
                                ?>
                                <article class="history-day card" data-history-day>
                                    <div class="history-day-heading">
                                        <h3><?= $historyDateTitle ?></h3>
                                        <div class="history-day-earnings" data-day-earnings>
                                            <?php if ((int) $day['reward_coins'] > 0 && (int) $day['total_coins'] !== (int) $day['reward_coins']): ?>
                                                <span class="history-day-reward">Награда +<?= (int) $day['reward_coins'] ?></span>
                                            <?php endif; ?>
                                            <?php if ((int) $day['total_coins'] > 0): ?>
                                                <span class="history-day-total">+<?= (int) $day['total_coins'] ?> монет</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php foreach ($day['entries'] as $entry): ?>
                                        <div class="history-entry" data-history-entry
                                             data-history-type="<?= $entry['type'] ?>"
                                             <?php if ($entry['challenge_id'] !== null): ?>data-history-challenge="<?= (int) $entry['challenge_id'] ?>"<?php endif; ?>>
                                            <span class="history-entry-icon <?= $entry['type'] === 'checkin' ? 'history-entry-icon-done' : 'history-entry-icon-task' ?>" aria-hidden="true">
                                                <?= $entry['type'] === 'checkin' ? '✓' : '◎' ?>
                                            </span>
                                            <div class="history-entry-info">
                                                <strong><?= htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span><?= $entry['type'] === 'checkin' ? 'Ежедневная отметка' : 'Ежедневное испытание' ?></span>
                                            </div>
                                            <?php if ($entry['type'] === 'task'): ?>
                                                <span class="history-entry-coins">+<?= (int) $entry['coins'] ?></span>
                                            <?php else: ?>
                                                <span class="history-entry-check" aria-label="Выполнено">✓</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <p class="empty-state history-filter-empty" data-history-empty hidden>У этого челленджа пока нет отметок.</p>
                    <?php endif; ?>
            </div>
        </section>
    </main>
    <footer class="site-footer">Habit Challenge · Маленькие шаги каждый день</footer>
</div>
<script>
(() => {
    const page = document.querySelector('.history-page-section');
    if (!page) return;
    const buttons = Array.from(page.querySelectorAll('[data-history-filter]'));
    const days = Array.from(page.querySelectorAll('[data-history-day]'));
    const empty = page.querySelector('[data-history-empty]');
    buttons.forEach(button => {
        button.addEventListener('click', () => {
            const selected = button.dataset.historyFilter;
            buttons.forEach(other => {
                const active = other === button;
                other.classList.toggle('is-selected', active);
                other.setAttribute('aria-pressed', String(active));
            });
            let visibleDays = 0;
            days.forEach(day => {
                const entries = Array.from(day.querySelectorAll('[data-history-entry]'));
                entries.forEach(entry => {
                    entry.hidden = selected !== 'all' &&
                        (entry.dataset.historyType !== 'checkin' ||
                         entry.dataset.historyChallenge !== selected);
                });
                const containsVisibleEntry = entries.some(entry => !entry.hidden);
                // Сохраняем дни только с общей наградой в режиме «Всё».
                const hasEarnings = !!day.querySelector('[data-day-earnings]')?.textContent.trim();
                const isVisible = containsVisibleEntry || (selected === 'all' && hasEarnings);
                day.hidden = !isVisible;
                day.querySelector('[data-day-earnings]').hidden = selected !== 'all';
                if (isVisible) visibleDays++;
            });
            if (empty) empty.hidden = visibleDays !== 0;
        });
    });
})();
</script>
</body>
</html>
