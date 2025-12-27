<?php
require_once __DIR__ . '/auth/session.php';
$page = $_GET['page'] ?? 'home';
$public_pages = ['login'];
if (!in_array($page, $public_pages, true)) {
    require_login();
}
$view = __DIR__ . "/views/{$page}.php";
if (!file_exists($view)) {
    $page = 'home';
    $view = __DIR__ . "/views/home.php";
}
$activePage = $page;
include __DIR__ . '/views/template.php';