<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';

require_login();

$userMatricula = (int)($_SESSION['user']['matricula'] ?? 0);
$action = $_POST['action'] ?? '';

$conn = db();

if ($action === 'mark_all') {
    $stmt = $conn->prepare('UPDATE notificacoes SET lida_em = NOW() WHERE matricula = ? AND lida_em IS NULL');
    $stmt->bind_param('i', $userMatricula);
    $stmt->execute();
    $stmt->close();
}

if ($action === 'mark_one') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE notificacoes SET lida_em = NOW() WHERE id = ? AND matricula = ?');
        $stmt->bind_param('ii', $id, $userMatricula);
        $stmt->execute();
        $stmt->close();
    }
}

if ($action === 'delete_read') {
    $stmt = $conn->prepare('DELETE FROM notificacoes WHERE matricula = ? AND lida_em IS NOT NULL');
    $stmt->bind_param('i', $userMatricula);
    $stmt->execute();
    $stmt->close();
}

header('Location: ../app.php?page=notificacoes');
exit;
