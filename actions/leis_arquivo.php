<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../config/db.php';

require_login();

if (!user_can_access_system('leis')) {
    http_response_code(403);
    exit('Sem permissão.');
}

$conn = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Lei inválida.');
}

$result = mysqli_execute_query(
    $conn,
    'SELECT arquivo_caminho, arquivo_nome_original, arquivo_mime FROM leis_repositorio WHERE id = ? LIMIT 1',
    [$id]
);
$row = $result ? mysqli_fetch_assoc($result) : null;
$relativePath = trim((string)($row['arquivo_caminho'] ?? ''));
$downloadName = trim((string)($row['arquivo_nome_original'] ?? 'lei.pdf'));
$mime = trim((string)($row['arquivo_mime'] ?? 'application/pdf'));

if ($relativePath === '') {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$fullPath = realpath(__DIR__ . '/../' . $relativePath);
$baseDir = realpath(__DIR__ . '/../uploads/leis');
if ($fullPath === false || $baseDir === false || strncmp($fullPath, $baseDir, strlen($baseDir)) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

header('Content-Type: ' . ($mime !== '' ? $mime : 'application/pdf'));
header('Content-Length: ' . (string)filesize($fullPath));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
readfile($fullPath);
exit;
