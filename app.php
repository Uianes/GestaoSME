<?php
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/auth/permissions.php';
$page = $_GET['page'] ?? 'home';
$public_pages = ['login'];
if (!in_array($page, $public_pages, true)) {
    require_login();
}
$links = system_links();
if ($page === 'admin' && !user_is_admin()) {
    $page = 'sem_permissao';
}
if (isset($links[$page]) && !user_can_access_system($page)) {
    $page = 'sem_permissao';
}
$view = __DIR__ . "/views/{$page}.php";
if (!file_exists($view)) {
    $page = 'home';
    $view = __DIR__ . "/views/home.php";
}
$activePage = $page;
include __DIR__ . '/views/template.php';
