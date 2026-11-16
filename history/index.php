<?php

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}

require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/controllers/HomeController.php';

$controller = new HomeController($conn);
$controller->history();
