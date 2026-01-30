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
    $_SESSION['flash_error'] = 'Assinatura já registrada.';
    header('Location: ../index.php?doc=' . $documentoId);
    exit;
}

// Confirma se não há assinantes anteriores pendentes
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

$hash = hash('sha256', $documentoId . '|' . $matricula . '|' . microtime(true));
$stmt = $conn->prepare('UPDATE doc_assinaturas SET status = "assinado", assinado_em = NOW(), assinatura_hash = ? WHERE id = ?');
$stmt->bind_param('si', $hash, $assinatura['id']);
$stmt->execute();
$stmt->close();

// Se todas assinaturas foram concluídas, marcar documento como assinado
$stmt = $conn->prepare("SELECT COUNT(*) AS pendentes FROM doc_assinaturas WHERE documento_id = ? AND status <> 'assinado'");
$stmt->bind_param('i', $documentoId);
$stmt->execute();
$pendentes = (int)($stmt->get_result()->fetch_assoc()['pendentes'] ?? 0);
$stmt->close();
if ($pendentes === 0) {
    $statusAssinado = 4;
    $stmt = $conn->prepare('UPDATE doc_documentos SET status_id = ? WHERE id = ?');
    $stmt->bind_param('ii', $statusAssinado, $documentoId);
    $stmt->execute();
    $stmt->close();

    // Envio automático após todas as assinaturas
    $stmt = $conn->prepare('SELECT id, tipo_id, id_unidade_origem, criado_por, status_id FROM doc_documentos WHERE id = ?');
    $stmt->bind_param('i', $documentoId);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($doc && (int)$doc['status_id'] !== 5) {
        $criador = (int)$doc['criado_por'];
        $tipoId = (int)$doc['tipo_id'];
        $idUnidade = (int)$doc['id_unidade_origem'];
        $ano = (int)date('Y');

        $stmt = $conn->prepare('SELECT 1 FROM doc_numeracao WHERE documento_id = ? LIMIT 1');
        $stmt->bind_param('i', $documentoId);
        $stmt->execute();
        $hasNumero = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$hasNumero) {
            $stmt = $conn->prepare('SELECT nome, usa_numero, prefixo FROM doc_tipos WHERE id = ?');
            $stmt->bind_param('i', $tipoId);
            $stmt->execute();
            $tipo = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($tipo && (int)$tipo['usa_numero'] === 1) {
                $stmt = $conn->prepare('SELECT id, proximo_numero FROM doc_sequencias WHERE tipo_id = ? AND id_unidade = ? AND ano = ? FOR UPDATE');
                $stmt->bind_param('iii', $tipoId, $idUnidade, $ano);
                $stmt->execute();
                $seq = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$seq) {
                    $inicio = 1;
                    $tipoNome = strtolower((string)($tipo['nome'] ?? ''));
                    if (strpos($tipoNome, 'memorando') !== false) {
                        $inicio = 48;
                    } elseif (strpos($tipoNome, 'ofício') !== false || strpos($tipoNome, 'oficio') !== false) {
                        $inicio = 2;
                    }
                    $stmt = $conn->prepare('INSERT INTO doc_sequencias (tipo_id, id_unidade, ano, proximo_numero) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('iiii', $tipoId, $idUnidade, $ano, $inicio);
                    $stmt->execute();
                    $seqId = $stmt->insert_id;
                    $numero = $inicio;
                    $stmt->close();
                } else {
                    $seqId = (int)$seq['id'];
                    $numero = (int)$seq['proximo_numero'];
                }

                $stmt = $conn->prepare('SELECT nome FROM unidade WHERE id_unidade = ?');
                $stmt->bind_param('i', $idUnidade);
                $stmt->execute();
                $unidadeNome = (string)($stmt->get_result()->fetch_assoc()['nome'] ?? '');
                $stmt->close();

                $prefixo = '';
                if ($unidadeNome !== '') {
                    $partes = preg_split('/\s+/', trim($unidadeNome));
                    $sigla = '';
                    foreach ($partes as $parte) {
                        $parte = preg_replace('/[^A-Za-zÀ-ÿ]/u', '', $parte);
                        if ($parte === '') {
                            continue;
                        }
                        if (function_exists('mb_substr')) {
                            $sigla .= mb_strtoupper(mb_substr($parte, 0, 1, 'UTF-8'), 'UTF-8');
                        } else {
                            $sigla .= strtoupper(substr($parte, 0, 1));
                        }
                    }
                    $prefixo = $sigla !== '' ? $sigla : $unidadeNome;
                }
                $prefixo = trim($prefixo);
                $codigo = $prefixo !== '' ? sprintf('%s-%04d/%d', $prefixo, $numero, $ano) : sprintf('%04d/%d', $numero, $ano);

                $stmt = $conn->prepare('INSERT INTO doc_numeracao (documento_id, sequencia_id, numero, ano, codigo_formatado) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('iiiis', $documentoId, $seqId, $numero, $ano, $codigo);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare('UPDATE doc_sequencias SET proximo_numero = proximo_numero + 1 WHERE id = ?');
                $stmt->bind_param('i', $seqId);
                $stmt->execute();
                $stmt->close();
            }
        }

        $statusEnviado = 5;
        $stmt = $conn->prepare('UPDATE doc_documentos SET status_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $statusEnviado, $documentoId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('SELECT id_unidade_destino, usuario_destino, tipo_destino, nome_externo, email_externo FROM doc_destinatarios WHERE documento_id = ?');
        $stmt->bind_param('i', $documentoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $destinos = [];
        while ($row = $result->fetch_assoc()) {
            $destinos[] = $row;
        }
        $stmt->close();

        $tramStmt = $conn->prepare('INSERT INTO doc_tramitacoes (documento_id, de_usuario, para_usuario, de_unidade, para_unidade, acao, despacho) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $deUnidade = (int)$doc['id_unidade_origem'];
        foreach ($destinos as $dest) {
            if ($dest['tipo_destino'] === 'interno') {
                $paraUsuario = $dest['usuario_destino'] ? (int)$dest['usuario_destino'] : null;
                $paraUnidade = $dest['id_unidade_destino'] ? (int)$dest['id_unidade_destino'] : null;
                $acao = 'enviado';
                $despacho = null;
                $tramStmt->bind_param('iiiiiss', $documentoId, $criador, $paraUsuario, $deUnidade, $paraUnidade, $acao, $despacho);
                $tramStmt->execute();
            }
        }
        $tramStmt->close();

        // Envios externos (email) no envio automático
        $envioStmt = $conn->prepare('INSERT INTO doc_envios (documento_id, canal, `para`, payload, status, criado_por) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($destinos as $dest) {
            if ($dest['tipo_destino'] !== 'externo') {
                continue;
            }
            $email = trim((string)($dest['email_externo'] ?? ''));
            if ($email === '') {
                continue;
            }
            $canal = 'email';
            $para = $email;
            $payload = json_encode(['nome' => $dest['nome_externo'] ?? null]);
            $status = 'pendente';
            $envioStmt->bind_param('issssi', $documentoId, $canal, $para, $payload, $status, $criador);
            $envioStmt->execute();

            $subject = 'Documento enviado - SME';
            $message = 'Você recebeu um documento do sistema SME. Em breve enviaremos detalhes adicionais.';
            $headers = 'From: no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'saeducacao.com.br');
            if (@mail($email, $subject, $message, $headers)) {
                $conn->query('UPDATE doc_envios SET status = "enviado", enviado_em = NOW() WHERE id = ' . (int)$envioStmt->insert_id);
            } else {
                $conn->query('UPDATE doc_envios SET status = "falhou" WHERE id = ' . (int)$envioStmt->insert_id);
            }
        }
        $envioStmt->close();

        // Auditoria
        $auditoria = [
            'acao' => 'envio_automatico',
            'documento_id' => $documentoId,
            'numero' => $hasNumero ? 'existente' : 'gerado',
            'destinos' => count($destinos)
        ];
        $auditoriaJson = json_encode($auditoria, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare('INSERT INTO doc_auditoria (entidade, entidade_id, usuario, evento, detalhes) VALUES (?, ?, ?, ?, ?)');
        $entidade = 'doc_documentos';
        $evento = 'envio_automatico';
        $stmt->bind_param('siiss', $entidade, $documentoId, $criador, $evento, $auditoriaJson);
        $stmt->execute();
        $stmt->close();
    }
}

$_SESSION['flash_success'] = $pendentes === 0 ? 'Assinatura registrada e documento enviado automaticamente.' : 'Assinatura registrada com sucesso.';
header('Location: ../index.php?doc=' . $documentoId);
exit;
