<?php
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../app.php?page=admin');
    exit;
}

if (!user_is_admin()) {
    $_SESSION['flash_error'] = 'Sem permissão de acesso.';
    header('Location: ../app.php?page=admin');
    exit;
}

$conn = db();
if (!permissions_table_exists($conn)) {
    $_SESSION['flash_error'] = 'A tabela usuarios_sistemas não existe.';
    header('Location: ../app.php?page=admin');
    exit;
}

$links = system_links();
$validKeys = array_keys($links);
$submitted = $_POST['access'] ?? [];
if (!is_array($submitted)) {
    $submitted = [];
}
$submittedAtivo = $_POST['ativo'] ?? [];
if (!is_array($submittedAtivo)) {
    $submittedAtivo = [];
}

$deleteStmt = $conn->prepare('DELETE FROM usuarios_sistemas WHERE matricula = ?');
$insertStmt = $conn->prepare('INSERT INTO usuarios_sistemas (matricula, sistema) VALUES (?, ?)');

$submittedMatriculas = array_map('intval', array_keys($submitted));
foreach ($submittedMatriculas as $matricula) {
    if (array_key_exists($matricula, $submittedAtivo)) {
        $ativo = (int)$submittedAtivo[$matricula] === 1 ? 1 : 0;
        $stmt = $conn->prepare('UPDATE usuarios SET ativo = ? WHERE matricula = ?');
        $stmt->bind_param('ii', $ativo, $matricula);
        $stmt->execute();
        $stmt->close();
    }

    $deleteStmt->bind_param('i', $matricula);
    $deleteStmt->execute();

    $selected = $submitted[$matricula] ?? [];
    if (!is_array($selected)) {
        $selected = [];
    }
    foreach ($selected as $systemKey) {
        if (!in_array($systemKey, $validKeys, true)) {
            continue;
        }
        $systemKey = (string)$systemKey;
        $insertStmt->bind_param('is', $matricula, $systemKey);
        $insertStmt->execute();
    }

    if ((int)($_SESSION['user']['matricula'] ?? 0) === $matricula) {
        unset($_SESSION['user_systems']);
    }
}

$deleteStmt->close();
$insertStmt->close();

$_SESSION['flash_success'] = 'Acessos atualizados com sucesso.';
header('Location: ../app.php?page=admin');
exit;
