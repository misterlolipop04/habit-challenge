<?php

declare(strict_types=1);

// Можно запускать локально: php tests/StreakRewardRulesTest.php
// Или без PHP на Mac, через запущенный Docker: 
// docker compose exec -T web php < tests/StreakRewardRulesTest.php
$rulesPath = __DIR__ . '/../app/models/StreakRewardRules.php';
if (!is_file($rulesPath)) {
    $rulesPath = '/var/www/app/models/StreakRewardRules.php';
}
require_once $rulesPath;

$passed = 0;

function check(string $name, int $expected, int $actual): void
{
    global $passed;
    if ($expected !== $actual) {
        throw new RuntimeException("FAILED {$name}: expected {$expected}, got {$actual}");
    }
    $passed++;
    echo "OK: {$name}\n";
}

function checkRejectsInvalidStreak(int $streak): void
{
    global $passed;
    try {
        StreakRewardRules::rewardCoins($streak);
    } catch (InvalidArgumentException $e) {
        $passed++;
        echo "OK: invalid streak {$streak} rejected\n";
        return;
    }
    throw new RuntimeException("FAILED: invalid streak {$streak} was accepted");
}

$yesterday = '2026-11-19';
check('first check-in', 1, StreakRewardRules::nextStreak(null, $yesterday, 0));
check('consecutive check-in', 6, StreakRewardRules::nextStreak($yesterday, $yesterday, 5));
check('streak breaks after a gap', 1, StreakRewardRules::nextStreak('2026-11-17', $yesterday, 8));
check('new streak after a longer gap', 1, StreakRewardRules::nextStreak('2026-10-01', $yesterday, 40));
check('streak at 7 becomes 8', 8, StreakRewardRules::nextStreak($yesterday, $yesterday, 7));
check('first day after gap ignores previous count', 1, StreakRewardRules::nextStreak(null, $yesterday, 100));

foreach ([1 => 10, 2 => 10, 6 => 10, 7 => 60, 8 => 10,
          13 => 10, 14 => 60, 21 => 60, 28 => 60, 100 => 10] as $streak => $coins) {
    check("reward at streak {$streak}", $coins, StreakRewardRules::rewardCoins($streak));
}
checkRejectsInvalidStreak(0);
checkRejectsInvalidStreak(-1);

echo "\nAll {$passed} reward and streak unit tests passed.\n";
