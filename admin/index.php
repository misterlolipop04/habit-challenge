<?php

declare(strict_types=1);

// Отдельная точка входа для администратора.
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/controllers/AdminController.php';

(new AdminController($conn))->handle();
