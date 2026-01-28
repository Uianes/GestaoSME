<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../routes.php';
require_once __DIR__ . '/../config/db.php';

function user_is_admin(): bool
{
    return !empty($_SESSION['user']['adm']) && (int)$_SESSION['user']['adm'] === 1;
}

function permissions_table_exists(mysqli $conn): bool
{
    $result = $conn->query("SHOW TABLES LIKE 'usuarios_sistemas'");
    return $result && $result->num_rows > 0;
}

function system_links(): array
{
    return require __DIR__ . '/../config/links.php';
}

function user_allowed_systems(int $matricula, ?mysqli $conn = null): array
{
    if ($matricula <= 0) {
        return [];
    }
    $db = $conn ?: db();
    if (!permissions_table_exists($db)) {
        return [];
    }
    $stmt = $db->prepare('SELECT sistema FROM usuarios_sistemas WHERE matricula = ?');
    $stmt->bind_param('i', $matricula);
    $stmt->execute();
    $res = $stmt->get_result();
    $systems = [];
    while ($row = $res->fetch_assoc()) {
        $systems[] = (string)$row['sistema'];
    }
    $stmt->close();
    return $systems;
}

function user_can_access_system(string $systemKey): bool
{
    if ($systemKey === 'home') {
        return true;
    }
    if (user_is_admin()) {
        return true;
    }
    $links = system_links();
    if (!isset($links[$systemKey])) {
        return true;
    }
    $matricula = (int)($_SESSION['user']['matricula'] ?? 0);
    if ($matricula <= 0) {
        return false;
    }
    $db = db();
    if (!permissions_table_exists($db)) {
        return true;
    }
    if (!isset($_SESSION['user_systems']) || !is_array($_SESSION['user_systems'])) {
        $_SESSION['user_systems'] = user_allowed_systems($matricula, $db);
    }
    return in_array($systemKey, $_SESSION['user_systems'], true);
}
