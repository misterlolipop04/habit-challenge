<?php

$host = getenv('DB_HOST') ?: 'db';
$database = getenv('DB_NAME') ?: 'daily_rewards';
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

if (!is_string($user) || $user === '' ||
    !is_string($password) || $password === '') {
    throw new RuntimeException('Не заданы DB_USER и DB_PASSWORD для подключения к MySQL.');
}

$conn = new mysqli($host, $user, $password, $database);
$conn->set_charset('utf8mb4');
