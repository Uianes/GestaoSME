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
$assunto = trim((string)($_POST['assunto'] ?? ''));
$confidencial = !empty($_POST['confidencial']) ? 1 : 0;
$conteudo = trim((string)($_POST['conteudo'] ?? ''));
$destUsuarios = $_POST['dest_usuarios'] ?? [];
$destUnidades = $_POST['dest_unidades'] ?? [];
$destExternos = $_POST['dest_externos'] ?? [];
$signUsuarios = $_POST['sign_usuarios'] ?? [];
$destUsuariosPronome = $_POST['dest_usuarios_pronome'] ?? [];
$destUnidadesPronome = $_POST['dest_unidades_pronome'] ?? [];

if ($matricula <= 0 || $documentoId <= 0 || $assunto === '') {
    $_SESSION['flash_error'] = 'Dados inválidos.';
    header('Location: ../index.php');
    exit;
}

$conn = db();
$conn->begin_transaction();
try {
    $stmt = $conn->prepare('SELECT id, criado_por FROM doc_documentos WHERE id = ?');
    $stmt->bind_param('i', $documentoId);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$doc || (int)$doc['criado_por'] !== $matricula) {
        throw new RuntimeException('Sem permissão para editar este documento.');
    }

    $stmt = $conn->prepare('UPDATE doc_documentos SET assunto = ?, confidencial = ? WHERE id = ?');
    $stmt->bind_param('sii', $assunto, $confidencial, $documentoId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM doc_destinatarios WHERE documento_id = ?');
    $stmt->bind_param('i', $documentoId);
    $stmt->execute();
    $stmt->close();

    if (is_array($destUsuarios)) {
        $stmt = $conn->prepare('INSERT INTO doc_destinatarios (documento_id, tipo_destino, ordem, usuario_destino, pronome_tratamento) VALUES (?, ?, ?, ?, ?)');
        $ordem = 1;
        foreach ($destUsuarios as $usuarioId) {
            $usuarioId = (int)$usuarioId;
            if ($usuarioId > 0) {
                $tipo = 'interno';
                $pronome = trim((string)($destUsuariosPronome[$usuarioId] ?? ''));
                $stmt->bind_param('isiis', $documentoId, $tipo, $ordem, $usuarioId, $pronome);
                $stmt->execute();
                $ordem++;
            }
        }
        $stmt->close();
    }

    if (is_array($destUnidades)) {
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

    $stmt = $conn->prepare('DELETE FROM doc_assinaturas WHERE documento_id = ?');
    $stmt->bind_param('i', $documentoId);
    $stmt->execute();
    $stmt->close();

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

    $stmt = $conn->prepare('SELECT COALESCE(MAX(numero_versao), 0) AS max_ver FROM doc_versoes WHERE documento_id = ?');
    $stmt->bind_param('i', $documentoId);
    $stmt->execute();
    $maxVer = (int)($stmt->get_result()->fetch_assoc()['max_ver'] ?? 0);
    $stmt->close();
    $novaVersao = $maxVer + 1;

    $stmt = $conn->prepare('INSERT INTO doc_versoes (documento_id, numero_versao, conteudo, criado_por) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('iisi', $documentoId, $novaVersao, $conteudo, $matricula);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM doc_assinaturas WHERE documento_id = ?');
    $stmt->bind_param('i', $documentoId);
    $stmt->execute();
    $assinaturasTotal = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    if ($assinaturasTotal > 0) {
        $stmt = $conn->prepare('UPDATE doc_assinaturas SET status = "pendente", assinado_em = NULL, assinatura_hash = NULL, observacao = NULL WHERE documento_id = ?');
        $stmt->bind_param('i', $documentoId);
        $stmt->execute();
        $stmt->close();

        $statusAssinatura = 3;
        $stmt = $conn->prepare('UPDATE doc_documentos SET status_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $statusAssinatura, $documentoId);
        $stmt->execute();
        $stmt->close();
    }

    $detalhes = json_encode(['assunto' => $assunto, 'versao' => $novaVersao], JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare('INSERT INTO doc_auditoria (entidade, entidade_id, usuario, evento, detalhes) VALUES ("documento", ?, ?, "edicao", ?)');
    $stmt->bind_param('iis', $documentoId, $matricula, $detalhes);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $_SESSION['flash_success'] = 'Documento atualizado e reenviado para assinatura.';
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash_error'] = 'Erro ao editar documento: ' . $e->getMessage();
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
}
