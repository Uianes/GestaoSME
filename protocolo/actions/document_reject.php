<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../auth/permissions.php';
require_once __DIR__ . '/../../config/db.php';

require_login();
if (!user_can_access_system('protocolo')) {
    $_SESSION['flash_error'] = 'Sem permissão de acesso.';
    header('Location: ../index.php');
    exit;
}

$matricula = (int)($_SESSION['user']['matricula'] ?? 0);
$documentoId = (int)($_POST['documento_id'] ?? 0);
$observacao = trim((string)($_POST['observacao'] ?? ''));
if ($matricula <= 0 || $documentoId <= 0) {
    $_SESSION['flash_error'] = 'Documento inválido.';
    header('Location: ../index.php');
    exit;
}

$conn = db();
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

if ($assinatura['status'] !== 'pendente') {
    $_SESSION['flash_error'] = 'A assinatura já foi registrada.';
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

$stmt = $conn->prepare('UPDATE doc_assinaturas SET status = "recusado", observacao = ?, assinado_em = NOW() WHERE id = ?');
$stmt->bind_param('si', $observacao, $assinatura['id']);
$stmt->execute();
$stmt->close();

$statusCancelado = 7;
$stmt = $conn->prepare('UPDATE doc_documentos SET status_id = ? WHERE id = ?');
$stmt->bind_param('ii', $statusCancelado, $documentoId);
$stmt->execute();
$stmt->close();

$_SESSION['flash_success'] = 'Assinatura recusada.';
header('Location: ../index.php?doc=' . $documentoId);
exit;
