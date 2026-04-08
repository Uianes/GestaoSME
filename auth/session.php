<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../routes.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    session_start();
}
function is_logged_in(): bool {
    return !empty($_SESSION['user']);
}
function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . url('app.php?page=login'));
        exit;
    }
}
