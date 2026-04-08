<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../auth/permissions.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../group_helpers.php';
require_once __DIR__ . '/../upload_helpers.php';

require_login();
if (!user_can_access_system('protocolo')) {
    $_SESSION['flash_error'] = 'Sem permissão de acesso.';
    header('Location: ../index.php');
    exit;
}

$matricula = (int)($_SESSION['user']['matricula'] ?? 0);
if ($matricula <= 0) {
    $_SESSION['flash_error'] = 'Sessão inválida.';
    header('Location: ../index.php');
    exit;
}

$tipoId = (int)($_POST['tipo_id'] ?? 0);
$assunto = trim((string)($_POST['assunto'] ?? ''));
$confidencial = !empty($_POST['confidencial']) ? 1 : 0;
$nivelSigilo = '';
$conteudo = trim((string)($_POST['conteudo'] ?? ''));
$unidadeOrigem = (int)($_POST['id_unidade_origem'] ?? 0);

$destUsuarios = $_POST['dest_usuarios'] ?? [];
$destUnidades = $_POST['dest_unidades'] ?? [];
$destGrupos = $_POST['dest_grupos'] ?? [];
$destExternos = $_POST['dest_externos'] ?? [];
$signUsuarios = $_POST['sign_usuarios'] ?? [];
$destUsuariosPronome = $_POST['dest_usuarios_pronome'] ?? [];
$destUnidadesPronome = $_POST['dest_unidades_pronome'] ?? [];

if ($tipoId <= 0 || $assunto === '' || $unidadeOrigem <= 0) {
    $_SESSION['flash_error'] = 'Preencha tipo, assunto e unidade de origem.';
    header('Location: ../index.php');
    exit;
}

$conn = db();
$groupsReady = proto_groups_schema_ready($conn);
$destUsuarios = is_array($destUsuarios) ? array_values(array_unique(array_filter(array_map('intval', $destUsuarios), static fn($id) => $id > 0))) : [];
$destUnidades = is_array($destUnidades) ? array_values(array_unique(array_filter(array_map('intval', $destUnidades), static fn($id) => $id > 0))) : [];
$destGrupos = is_array($destGrupos) ? array_values(array_unique(array_filter(array_map('intval', $destGrupos), static fn($id) => $id > 0))) : [];
$groupUserIds = $groupsReady ? proto_expand_group_user_ids($conn, $destGrupos) : [];
$totalDest = 0;
foreach (array_values(array_unique(array_merge($destUsuarios, $groupUserIds))) as $usuarioId) {
    if ((int)$usuarioId > 0) {
        $totalDest++;
    }
}
foreach ($destUnidades as $unidadeId) {
    if ((int)$unidadeId > 0) {
        $totalDest++;
    }
}
if (is_array($destExternos)) {
    foreach ($destExternos as $externo) {
        if (!is_array($externo)) {
            continue;
        }
        $nome = trim((string)($externo['nome'] ?? ''));
        $email = trim((string)($externo['email'] ?? ''));
        if ($nome !== '' || $email !== '') {
            $totalDest++;
        }
    }
}

$stmt = $conn->prepare('SELECT id, nome FROM doc_tipos WHERE id = ?');
$stmt->bind_param('i', $tipoId);
$stmt->execute();
$tipoAtual = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($tipoAtual && strtolower((string)$tipoAtual['nome']) === 'memorando' && $totalDest > 1) {
    $stmt = $conn->prepare('SELECT id FROM doc_tipos WHERE nome = ? LIMIT 1');
    $tipoNome = 'Memorando Circular';
    $stmt->bind_param('s', $tipoNome);
    $stmt->execute();
    $tipoCircular = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($tipoCircular) {
        $tipoId = (int)$tipoCircular['id'];
    }
}
$conn->begin_transaction();
try {
    $statusId = 1; // rascunho
    $stmt = $conn->prepare('INSERT INTO doc_documentos (tipo_id, id_unidade_origem, criado_por, status_id, assunto, confidencial, nivel_sigilo) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iiiisis', $tipoId, $unidadeOrigem, $matricula, $statusId, $assunto, $confidencial, $nivelSigilo);
    $stmt->execute();
    $documentoId = $stmt->insert_id;
    $stmt->close();

    if ($conteudo !== '') {
        $stmt = $conn->prepare('INSERT INTO doc_versoes (documento_id, numero_versao, conteudo, criado_por) VALUES (?, ?, ?, ?)');
        $versao = 1;
        $stmt->bind_param('iisi', $documentoId, $versao, $conteudo, $matricula);
        $stmt->execute();
        $stmt->close();
    }

    if (!empty($_FILES['anexos'])) {
        proto_store_attachments($conn, (int)$documentoId, $matricula, $_FILES['anexos'], 'anexos');
    }

    if ($groupsReady && !empty($destGrupos)) {
        $stmt = $conn->prepare('INSERT INTO doc_documento_grupos (documento_id, grupo_id) VALUES (?, ?)');
        foreach ($destGrupos as $grupoId) {
            $stmt->bind_param('ii', $documentoId, $grupoId);
            $stmt->execute();
        }
        $stmt->close();
    }

    $insertedUserIds = [];
    if (!empty($destUsuarios)) {
        $stmt = $conn->prepare('INSERT INTO doc_destinatarios (documento_id, tipo_destino, ordem, usuario_destino, pronome_tratamento) VALUES (?, ?, ?, ?, ?)');
        $ordem = 1;
        foreach ($destUsuarios as $usuarioId) {
            $tipo = 'interno';
            $pronome = trim((string)($destUsuariosPronome[$usuarioId] ?? ''));
            $stmt->bind_param('isiis', $documentoId, $tipo, $ordem, $usuarioId, $pronome);
            $stmt->execute();
            $insertedUserIds[$usuarioId] = true;
            $ordem++;
        }
        foreach ($groupUserIds as $usuarioId) {
            if (!empty($insertedUserIds[$usuarioId])) {
                continue;
            }
            $tipo = 'interno';
            $pronome = '';
            $stmt->bind_param('isiis', $documentoId, $tipo, $ordem, $usuarioId, $pronome);
            $stmt->execute();
            $insertedUserIds[$usuarioId] = true;
            $ordem++;
        }
        $stmt->close();
    } elseif (!empty($groupUserIds)) {
        $stmt = $conn->prepare('INSERT INTO doc_destinatarios (documento_id, tipo_destino, ordem, usuario_destino, pronome_tratamento) VALUES (?, ?, ?, ?, ?)');
        $ordem = 1;
        foreach ($groupUserIds as $usuarioId) {
            $tipo = 'interno';
            $pronome = '';
            $stmt->bind_param('isiis', $documentoId, $tipo, $ordem, $usuarioId, $pronome);
            $stmt->execute();
            $ordem++;
        }
        $stmt->close();
    }

    if (is_array($signUsuarios)) {
        $stmt = $conn->prepare('INSERT INTO doc_assinaturas (documento_id, usuario, ordem) VALUES (?, ?, ?)');
        $ordem = 1;
        foreach ($signUsuarios as $usuarioId) {
            $usuarioId = (int)$usuarioId;
            if ($usuarioId > 0) {
                $stmt->bind_param('iii', $documentoId, $usuarioId, $ordem);
                $stmt->execute();
                $ordem++;
            }
        }
        $stmt->close();
    }

    if (!empty($destUnidades)) {
        $stmt = $conn->prepare('INSERT INTO doc_destinatarios (documento_id, tipo_destino, ordem, id_unidade_destino, pronome_tratamento) VALUES (?, ?, ?, ?, ?)');
        $ordem = 1;
        foreach ($destUnidades as $unidadeId) {
            $unidadeId = (int)$unidadeId;
            if ($unidadeId > 0) {
                $tipo = 'interno';
                $pronome = trim((string)($destUnidadesPronome[$unidadeId] ?? ''));
                $stmt->bind_param('isiis', $documentoId, $tipo, $ordem, $unidadeId, $pronome);
                $stmt->execute();
                $ordem++;
            }
        }
        $stmt->close();
    }

    if (is_array($destExternos)) {
        $stmt = $conn->prepare('INSERT INTO doc_destinatarios (documento_id, tipo_destino, ordem, nome_externo, orgao_externo, email_externo, endereco_externo, pronome_tratamento) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $ordem = 1;
        foreach ($destExternos as $externo) {
            if (!is_array($externo)) {
                continue;
            }
            $nome = trim((string)($externo['nome'] ?? ''));
            $orgao = trim((string)($externo['orgao'] ?? ''));
            $email = trim((string)($externo['email'] ?? ''));
            $endereco = trim((string)($externo['endereco'] ?? ''));
            $pronome = trim((string)($externo['pronome'] ?? ''));
            if ($nome === '' && $email === '') {
                continue;
            }
            $tipo = 'externo';
            $stmt->bind_param('isisssss', $documentoId, $tipo, $ordem, $nome, $orgao, $email, $endereco, $pronome);
            $stmt->execute();
            $ordem++;
        }
        $stmt->close();
    }

    $conn->commit();
    $_SESSION['flash_success'] = 'Documento criado em rascunho.';
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash_error'] = 'Erro ao criar documento: ' . $e->getMessage();
    header('Location: ../index.php');
    exit;
}
