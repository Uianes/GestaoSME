<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/permissions.php';

require_login();

if (!user_can_access_system('alunos_inclusao')) {
    http_response_code(403);
    echo 'Sem permissão para acessar este laudo.';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Registro inválido.';
    exit;
}

$conn = db();
$stmt = $conn->prepare('SELECT laudo_caminho, laudo_nome_original, laudo_mime FROM alunos_aee WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo 'Falha ao consultar o laudo.';
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row || empty($row['laudo_caminho'])) {
    http_response_code(404);
    echo 'Laudo não encontrado.';
    exit;
}

$relativePath = (string)$row['laudo_caminho'];
$baseDir = realpath(__DIR__ . '/../uploads/alunos_aee_laudos');
$fullPath = realpath(__DIR__ . '/../' . $relativePath);
if ($baseDir === false || $fullPath === false || strncmp($fullPath, $baseDir, strlen($baseDir)) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    echo 'Arquivo do laudo não encontrado.';
    exit;
}

$mime = (string)($row['laudo_mime'] ?? '');
if ($mime === '') {
    $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
}
$name = (string)($row['laudo_nome_original'] ?? basename($fullPath));

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($fullPath));
header('Content-Disposition: inline; filename="' . rawurlencode($name) . '"');
readfile($fullPath);
exit;
