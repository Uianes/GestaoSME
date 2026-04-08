<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../auth/permissions.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../upload_helpers.php';

require_login();
if (!user_can_access_system('protocolo')) {
    $_SESSION['flash_error'] = 'Sem permissão de acesso.';
    header('Location: ../index.php');
    exit;
}

$matricula = (int)($_SESSION['user']['matricula'] ?? 0);
$documentoId = (int)($_POST['documento_id'] ?? 0);
if ($matricula <= 0 || $documentoId <= 0) {
    $_SESSION['flash_error'] = 'Documento inválido.';
    header('Location: ../index.php');
    exit;
}

if (empty($_FILES['anexos']) && empty($_FILES['anexo'])) {
    $_SESSION['flash_error'] = 'Selecione um arquivo válido.';
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
}

$conn = db();
$stmt = $conn->prepare('
    SELECT 1
    FROM doc_documentos d
    LEFT JOIN doc_destinatarios dd ON dd.documento_id = d.id
    LEFT JOIN doc_permissoes dp ON dp.documento_id = d.id
    LEFT JOIN vinculo v ON v.matricula = ?
    WHERE d.id = ?
      AND (
        d.criado_por = ?
        OR dd.usuario_destino = ?
        OR dp.usuario = ?
        OR (dd.id_unidade_destino IS NOT NULL AND dd.id_unidade_destino = v.id_unidade)
      )
    LIMIT 1
');
$stmt->bind_param('iiiii', $matricula, $documentoId, $matricula, $matricula, $matricula);
$stmt->execute();
$allowed = (bool)$stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$allowed) {
    $_SESSION['flash_error'] = 'Sem permissão para anexar neste documento.';
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
}

try {
    $files = !empty($_FILES['anexos']) ? $_FILES['anexos'] : $_FILES['anexo'];
    proto_store_attachments($conn, $documentoId, $matricula, $files, 'anexos');
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
}

$_SESSION['flash_success'] = 'Anexo(s) enviado(s) com sucesso.';
header('Location: ../index.php?doc=' . $documentoId);
exit;
