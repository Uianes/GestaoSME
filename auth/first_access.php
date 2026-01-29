<?php
require_once __DIR__ . '/../config/db.php';

function first_access_has_column(mysqli $conn, string $table, string $column): bool
{
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $columnSafe = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
    $has = $result && (bool)$result->fetch_assoc();
    return $has;
}

function first_access_password_is_hashed(string $value): bool
{
    return str_starts_with($value, '$2y$') || str_starts_with($value, '$argon2');
}

function first_access_needs_update(int $matricula, ?mysqli $conn = null): bool
{
    if ($matricula <= 0) {
        return false;
    }
    $db = $conn ?: db();
    $hasFlag = first_access_has_column($db, 'usuarios', 'senha_alterada');

    $sql = 'SELECT email, telefone, senha' . ($hasFlag ? ', senha_alterada' : '') . ' FROM usuarios WHERE matricula = ? LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $matricula);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return false;
    }

    $email = trim((string)($row['email'] ?? ''));
    $telefone = trim((string)($row['telefone'] ?? ''));
    $senha = (string)($row['senha'] ?? '');

    if ($hasFlag && (int)($row['senha_alterada'] ?? 0) === 0) {
        return true;
    }

    if ($email === '' || $telefone === '') {
        return true;
    }

    if ($senha !== '' && !first_access_password_is_hashed($senha)) {
        return true;
    }

    return false;
}

function first_access_update_user(int $matricula, string $email, string $telefone, string $senhaPlain): bool
{
    if ($matricula <= 0) {
        return false;
    }
    $db = db();
    $hasFlag = first_access_has_column($db, 'usuarios', 'senha_alterada');

    $hashed = password_hash($senhaPlain, PASSWORD_DEFAULT);
    if ($hasFlag) {
        $stmt = $db->prepare('UPDATE usuarios SET email = ?, telefone = ?, senha = ?, senha_alterada = 1 WHERE matricula = ?');
        $stmt->bind_param('sssi', $email, $telefone, $hashed, $matricula);
    } else {
        $stmt = $db->prepare('UPDATE usuarios SET email = ?, telefone = ?, senha = ? WHERE matricula = ?');
        $stmt->bind_param('sssi', $email, $telefone, $hashed, $matricula);
    }
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
