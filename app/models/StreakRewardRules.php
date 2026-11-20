<?php

declare(strict_types=1);

final class StreakRewardRules
{
    public static function nextStreak(?string $lastDate, string $yesterday, int $currentStreak): int
    {
        return $lastDate === $yesterday ? $currentStreak + 1 : 1;
    }

    public static function rewardCoins(int $streak): int
    {
        if ($streak < 1) {
            throw new InvalidArgumentException('Стрик для награды должен быть положительным.');
        }

        return 10 + ($streak % 7 === 0 ? 50 : 0);
    }
}
