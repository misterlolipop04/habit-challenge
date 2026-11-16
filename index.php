<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/controllers/HomeController.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('habit_site');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();
if (!isset($_SESSION['site_csrf']) || !is_string($_SESSION['site_csrf'])) {
    $_SESSION['site_csrf'] = bin2hex(random_bytes(32));
}

$controller = new HomeController($conn);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['site_csrf'], $token)) {
        http_response_code(403);
        echo 'Недействительный запрос. Обновите страницу.';
        exit;
    }
    $action = $_POST['action'] ?? '';
    if (!is_string($action)) {
        http_response_code(400);
        exit;
    }
    switch ($action) {
        case 'complete_task':
            $controller->completeTask();
            break;
        case 'update_challenge_name':
            $controller->updateChallengeName();
            break;
        case 'create_challenge':
            $controller->createChallenge();
            break;
        case 'select_challenge':
            $controller->selectChallenge();
            break;
        case 'complete_challenge':
            $controller->completeChallenge();
            break;
        default:
            http_response_code(400);
            echo 'Неизвестное действие';
    }
} else {
    $controller->index();
}
