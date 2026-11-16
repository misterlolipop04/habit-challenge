<?php

$host = 'db';
$user = 'user';
$password = 'password';
$database = 'daily_rewards';

$conn = new mysqli($host, $user, $password, $database);

$conn->set_charset('utf8mb4');