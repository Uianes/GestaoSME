<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../auth/permissions.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../govbr_signature_helpers.php';

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

$conn = db();
if (!proto_govbr_signing_enabled()) {
    $_SESSION['flash_error'] = 'Integração GOV.BR não configurada.';
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
}

if (!proto_govbr_schema_ready($conn)) {
    $_SESSION['flash_error'] = 'Banco de dados sem as colunas necessárias para assinatura GOV.BR.';
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
}

$stmt = $conn->prepare('SELECT id, status, ordem FROM doc_assinaturas WHERE documento_id = ? AND usuario = ?');
$stmt->bind_param('ii', $documentoId, $matricula);
$stmt->execute();
$assinatura = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assinatura) {
    $_SESSION['flash_error'] = 'Você não está na lista de assinantes.';
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
}

if (($assinatura['status'] ?? '') !== 'pendente') {
    $_SESSION['flash_error'] = 'Assinatura já registrada.';
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
}

$stmt = $conn->prepare('SELECT 1 FROM doc_assinaturas WHERE documento_id = ? AND ordem < ? AND status <> "assinado" LIMIT 1');
$stmt->bind_param('ii', $documentoId, $assinatura['ordem']);
$stmt->execute();
$pendingPrev = (bool)$stmt->get_result()->fetch_assoc();
$stmt->close();

if ($pendingPrev) {
    $_SESSION['flash_error'] = 'Há assinaturas pendentes antes da sua.';
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
}

try {
    $url = proto_govbr_build_authorization_url($documentoId, (int)$assinatura['id']);
    header('Location: ' . $url);
    exit;
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Erro ao iniciar assinatura GOV.BR: ' . $e->getMessage();
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
}
