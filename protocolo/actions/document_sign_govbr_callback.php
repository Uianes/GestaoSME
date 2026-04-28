<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../auth/permissions.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../mail_helpers.php';
require_once __DIR__ . '/../govbr_signature_helpers.php';
require_once __DIR__ . '/../document_pdf_helpers.php';

require_login();
if (!user_can_access_system('protocolo')) {
    $_SESSION['flash_error'] = 'Sem permissão de acesso.';
    header('Location: ../index.php');
    exit;
}

$matricula = (int)($_SESSION['user']['matricula'] ?? 0);
$state = trim((string)($_GET['state'] ?? ''));
$code = trim((string)($_GET['code'] ?? ''));
$error = trim((string)($_GET['error'] ?? ''));
$errorDescription = trim((string)($_GET['error_description'] ?? ''));

$documentoId = 0;
try {
    if ($state === '') {
        throw new RuntimeException('Retorno GOV.BR sem state.');
    }

    $sessionData = proto_govbr_consume_session($state);
    $documentoId = (int)($sessionData['documento_id'] ?? 0);
    $assinaturaId = (int)($sessionData['assinatura_id'] ?? 0);

    if ($error !== '') {
        throw new RuntimeException($errorDescription !== '' ? $errorDescription : $error);
    }
    if ($code === '') {
        throw new RuntimeException('Retorno GOV.BR sem código de autorização.');
    }
    if ($matricula <= 0 || $documentoId <= 0 || $assinaturaId <= 0) {
        throw new RuntimeException('Dados inválidos para concluir a assinatura GOV.BR.');
    }

    $conn = db();
    if (!proto_govbr_schema_ready($conn)) {
        throw new RuntimeException('Banco de dados sem suporte para assinatura GOV.BR.');
    }

    $stmt = $conn->prepare('SELECT id, usuario, status, ordem FROM doc_assinaturas WHERE id = ? AND documento_id = ?');
    $stmt->bind_param('ii', $assinaturaId, $documentoId);
    $stmt->execute();
    $assinatura = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$assinatura || (int)($assinatura['usuario'] ?? 0) !== $matricula) {
        throw new RuntimeException('Você não está autorizado a concluir esta assinatura.');
    }
    if (($assinatura['status'] ?? '') !== 'pendente') {
        throw new RuntimeException('Assinatura já registrada.');
    }

    $stmt = $conn->prepare('SELECT 1 FROM doc_assinaturas WHERE documento_id = ? AND ordem < ? AND status <> "assinado" LIMIT 1');
    $ordem = (int)($assinatura['ordem'] ?? 0);
    $stmt->bind_param('ii', $documentoId, $ordem);
    $stmt->execute();
    $pendingPrev = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($pendingPrev) {
        throw new RuntimeException('Há assinaturas pendentes antes da sua.');
    }

    $tokenData = proto_govbr_exchange_code_for_token($code);
    $accessToken = (string)($tokenData['access_token'] ?? '');
    if ($accessToken === '') {
        throw new RuntimeException('Token GOV.BR não retornado.');
    }

    $pdf = proto_build_document_pdf($conn, $documentoId);
    $pdfBinary = (string)($pdf['pdf_binary'] ?? '');
    if ($pdfBinary === '') {
        throw new RuntimeException('Não foi possível gerar o PDF do documento para assinatura.');
    }

    $hashRaw = hash('sha256', $pdfBinary, true);
    $hashHex = hash('sha256', $pdfBinary);
    $hashBase64 = base64_encode($hashRaw);

    $certificado = proto_govbr_fetch_certificate($accessToken);
    $assinaturaPkcs7 = proto_govbr_sign_hash($accessToken, $hashBase64);
    $arquivoAssinatura = proto_govbr_store_signature_file($documentoId, $assinaturaId, 'p7s', $assinaturaPkcs7);

    $conn->begin_transaction();

    $provedor = 'govbr';
    $provedorRef = basename($arquivoAssinatura);
    $assinaturaMime = 'application/pkcs7-signature';
    $stmt = $conn->prepare('
        UPDATE doc_assinaturas
        SET status = "assinado",
            assinado_em = NOW(),
            assinatura_hash = ?,
            observacao = NULL,
            provedor = ?,
            provedor_ref = ?,
            certificado_publico = ?,
            arquivo_assinatura = ?,
            assinatura_mime = ?
        WHERE id = ?
    ');
    $stmt->bind_param(
        'ssssssi',
        $hashHex,
        $provedor,
        $provedorRef,
        $certificado,
        $arquivoAssinatura,
        $assinaturaMime,
        $assinaturaId
    );
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS pendentes FROM doc_assinaturas WHERE documento_id = ? AND status <> 'assinado'");
    $stmt->bind_param('i', $documentoId);
    $stmt->execute();
    $pendentes = (int)($stmt->get_result()->fetch_assoc()['pendentes'] ?? 0);
    $stmt->close();

    $mailResult = null;
    if ($pendentes === 0) {
        $statusAssinado = 4;
        $stmt = $conn->prepare('UPDATE doc_documentos SET status_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $statusAssinado, $documentoId);
        $stmt->execute();
        $stmt->close();

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
                if (($dest['tipo_destino'] ?? '') === 'interno') {
                    $paraUsuario = !empty($dest['usuario_destino']) ? (int)$dest['usuario_destino'] : null;
                    $paraUnidade = !empty($dest['id_unidade_destino']) ? (int)$dest['id_unidade_destino'] : null;
                    $acao = 'enviado';
                    $despacho = null;
                    $tramStmt->bind_param('iiiiiss', $documentoId, $criador, $paraUsuario, $deUnidade, $paraUnidade, $acao, $despacho);
                    $tramStmt->execute();
                }
            }
            $tramStmt->close();

            $mailResult = proto_send_document_emails($conn, $documentoId, $criador, $destinos);

            $auditoria = [
                'acao' => 'envio_automatico',
                'documento_id' => $documentoId,
                'numero' => $hasNumero ? 'existente' : 'gerado',
                'destinos' => count($destinos),
                'emails_enviados' => $mailResult['sent'] ?? 0,
                'emails_falharam' => $mailResult['failed'] ?? 0,
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

    $detalhes = json_encode([
        'provedor' => 'govbr',
        'arquivo_assinatura' => $arquivoAssinatura,
        'validacao_externa' => proto_govbr_validation_url(),
    ], JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare('INSERT INTO doc_auditoria (entidade, entidade_id, usuario, evento, detalhes) VALUES ("documento", ?, ?, "assinatura_govbr", ?)');
    $stmt->bind_param('iis', $documentoId, $matricula, $detalhes);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    $_SESSION['flash_success'] = $pendentes === 0
        ? 'Assinatura GOV.BR registrada e documento enviado automaticamente.'
            . ($mailResult !== null && ($mailResult['total'] ?? 0) > 0 ? ' E-mail(s): ' . $mailResult['sent'] . ' enviado(s), ' . $mailResult['failed'] . ' falha(s).' : '')
        : 'Assinatura GOV.BR registrada com sucesso.';
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli && $conn->errno === 0) {
        // noop
    }
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }
    }
    $_SESSION['flash_error'] = 'Erro ao concluir assinatura GOV.BR: ' . $e->getMessage();
}

header('Location: ../index.php?doc=' . $documentoId);
exit;
