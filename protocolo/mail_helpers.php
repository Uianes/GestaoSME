<?php
require_once __DIR__ . '/../routes.php';
require_once __DIR__ . '/../config/db.php';

if (!function_exists('proto_mail_base_app_url')) {
    function proto_mail_base_app_url(): string
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/protocolo/actions/document_send.php';
        $basePath = dirname(dirname(dirname(str_replace('\\', '/', $scriptName))));
        $basePath = rtrim($basePath, '/');
        if ($basePath === DIRECTORY_SEPARATOR || $basePath === '\\' || $basePath === '.') {
            $basePath = '';
        }
        return $scheme . '://' . $host . $basePath;
    }
}

if (!function_exists('proto_mail_sender_email')) {
    function proto_mail_sender_email(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'saeducacao.com.br';
        $host = preg_replace('/:\d+$/', '', $host);
        return 'no-reply@' . $host;
    }
}

if (!function_exists('proto_collect_document_emails')) {
    function proto_collect_document_emails(mysqli $conn, array $destinos): array
    {
        $emails = [];
        $internalIds = [];

        foreach ($destinos as $dest) {
            if (($dest['tipo_destino'] ?? '') === 'interno' && !empty($dest['usuario_destino'])) {
                $internalIds[] = (int)$dest['usuario_destino'];
            }
        }

        $internalIds = array_values(array_unique(array_filter($internalIds, static fn($id) => $id > 0)));
        $internalUsers = [];
        if (!empty($internalIds)) {
            $sql = 'SELECT matricula, nome, email FROM usuarios WHERE matricula IN (' . implode(',', $internalIds) . ')';
            $result = $conn->query($sql);
            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    $internalUsers[(int)$row['matricula']] = $row;
                }
            }
        }

        foreach ($destinos as $dest) {
            $tipo = (string)($dest['tipo_destino'] ?? '');
            if ($tipo === 'interno') {
                $usuarioId = (int)($dest['usuario_destino'] ?? 0);
                if ($usuarioId <= 0 || empty($internalUsers[$usuarioId])) {
                    continue;
                }
                $email = trim((string)($internalUsers[$usuarioId]['email'] ?? ''));
                if ($email === '') {
                    continue;
                }
                $emails[strtolower($email)] = [
                    'email' => $email,
                    'nome' => trim((string)($internalUsers[$usuarioId]['nome'] ?? '')),
                    'tipo' => 'interno',
                ];
                continue;
            }

            if ($tipo === 'externo') {
                $email = trim((string)($dest['email_externo'] ?? ''));
                if ($email === '') {
                    continue;
                }
                $emails[strtolower($email)] = [
                    'email' => $email,
                    'nome' => trim((string)($dest['nome_externo'] ?? '')),
                    'tipo' => 'externo',
                ];
            }
        }

        return array_values($emails);
    }
}

if (!function_exists('proto_send_document_emails')) {
    function proto_generate_document_pdf(mysqli $conn, int $documentoId): array
    {
        $stmt = $conn->prepare('
            SELECT d.*, t.nome AS tipo_nome, uo.nome AS unidade_origem_nome
            FROM doc_documentos d
            INNER JOIN doc_tipos t ON t.id = d.tipo_id
            LEFT JOIN unidade uo ON uo.id_unidade = d.id_unidade_origem
            WHERE d.id = ?
            LIMIT 1
        ');
        $stmt->bind_param('i', $documentoId);
        $stmt->execute();
        $documento = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$documento) {
            throw new RuntimeException('Documento não encontrado para gerar PDF.');
        }

        $stmt = $conn->prepare('SELECT * FROM doc_versoes WHERE documento_id = ? ORDER BY numero_versao DESC LIMIT 1');
        $stmt->bind_param('i', $documentoId);
        $stmt->execute();
        $versao = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare('SELECT codigo_formatado, numero, ano FROM doc_numeracao WHERE documento_id = ? LIMIT 1');
        $stmt->bind_param('i', $documentoId);
        $stmt->execute();
        $numeracao = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare('
            SELECT dd.*, u.nome AS usuario_nome, un.nome AS unidade_nome
            FROM doc_destinatarios dd
            LEFT JOIN usuarios u ON u.matricula = dd.usuario_destino
            LEFT JOIN unidade un ON un.id_unidade = dd.id_unidade_destino
            WHERE dd.documento_id = ?
            ORDER BY dd.ordem
        ');
        $stmt->bind_param('i', $documentoId);
        $stmt->execute();
        $destResult = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $destinatarios = [];
        foreach ($destResult as $dest) {
            $prefixo = trim((string)($dest['pronome_tratamento'] ?? ''));
            $prefixo = $prefixo !== '' ? $prefixo . ' – ' : '';
            if (($dest['tipo_destino'] ?? '') === 'interno') {
                if (!empty($dest['usuario_destino'])) {
                    $destinatarios[] = $prefixo . (($dest['usuario_nome'] ?? '') ?: ('Usuário #' . (int)$dest['usuario_destino']));
                } elseif (!empty($dest['id_unidade_destino'])) {
                    $destinatarios[] = $prefixo . (($dest['unidade_nome'] ?? '') ?: ('Unidade #' . (int)$dest['id_unidade_destino']));
                }
                continue;
            }
            $nome = trim((string)($dest['nome_externo'] ?? ''));
            $orgao = trim((string)($dest['orgao_externo'] ?? ''));
            $destinatarios[] = $prefixo . ($orgao !== '' ? ($nome . ' - ' . $orgao) : $nome);
        }
        $destinatarios = array_values(array_filter(array_unique($destinatarios)));

        $stmt = $conn->prepare('
            SELECT a.assinado_em, u.nome, v.cargo
            FROM doc_assinaturas a
            INNER JOIN usuarios u ON u.matricula = a.usuario
            LEFT JOIN vinculo v ON v.matricula = a.usuario
            WHERE a.documento_id = ? AND a.status = "assinado"
            ORDER BY a.assinado_em DESC
            LIMIT 1
        ');
        $stmt->bind_param('i', $documentoId);
        $stmt->execute();
        $assinatura = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $assinaturaInfo = [];
        if ($assinatura) {
            $assinaturaInfo = [
                'nome' => $assinatura['nome'] ?? '',
                'cargo' => $assinatura['cargo'] ?? '',
                'data' => !empty($assinatura['assinado_em']) ? date('d/m/Y H:i', strtotime($assinatura['assinado_em'])) : '',
            ];
        }

        $unidadeOrigem = $documento['unidade_origem_nome'] ?? '';
        $logoFile = __DIR__ . '/../img/brasao-santo-augusto.png';
        $logoSrc = '';
        if (file_exists($logoFile)) {
            $imageData = base64_encode(file_get_contents($logoFile));
            $logoSrc = 'data:image/png;base64,' . $imageData;
        }

        ob_start();
        require __DIR__ . '/templates/documento.php';
        $html = ob_get_clean();

        require_once __DIR__ . '/../patrimonio/vendor/autoload.php';
        $dompdf = new Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $tipoNome = $documento['tipo_nome'] ?? 'documento';
        $codigo = $numeracao['codigo_formatado'] ?? 'sem-numero';
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($tipoNome . '-' . $codigo));
        $filename = trim($safe, '-') . '.pdf';

        return [
            'filename' => $filename,
            'content' => $dompdf->output(),
        ];
    }
}

if (!function_exists('proto_send_document_emails')) {
    function proto_send_document_emails(mysqli $conn, int $documentoId, int $criadoPor, array $destinos): array
    {
        if ($documentoId <= 0 || $criadoPor <= 0) {
            return ['sent' => 0, 'failed' => 0, 'total' => 0];
        }

        $stmt = $conn->prepare('
            SELECT d.assunto, t.nome AS tipo_nome, n.codigo_formatado,
                   (
                       SELECT dv.conteudo
                       FROM doc_versoes dv
                       WHERE dv.documento_id = d.id
                       ORDER BY dv.numero_versao DESC, dv.criado_em DESC
                       LIMIT 1
                   ) AS conteudo
            FROM doc_documentos d
            LEFT JOIN doc_tipos t ON t.id = d.tipo_id
            LEFT JOIN doc_numeracao n ON n.documento_id = d.id
            WHERE d.id = ?
            LIMIT 1
        ');
        $stmt->bind_param('i', $documentoId);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        $assuntoDocumento = trim((string)($doc['assunto'] ?? 'Documento'));
        $tipoNome = trim((string)($doc['tipo_nome'] ?? 'Documento'));
        $codigo = trim((string)($doc['codigo_formatado'] ?? ''));
        $conteudoHtml = trim((string)($doc['conteudo'] ?? ''));
        $conteudoHtml = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $conteudoHtml ?? '');
        $conteudoHtml = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $conteudoHtml ?? '');
        $conteudoHtml = trim((string)$conteudoHtml);
        $conteudoTexto = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $conteudoHtml)), ENT_QUOTES, 'UTF-8'));
        $subject = $codigo !== ''
            ? $tipoNome . ' ' . $codigo . ' - ' . $assuntoDocumento
            : $tipoNome . ' - ' . $assuntoDocumento;
        $from = proto_mail_sender_email();
        $pdf = proto_generate_document_pdf($conn, $documentoId);

        $destinatarios = proto_collect_document_emails($conn, $destinos);
        if (empty($destinatarios)) {
            return ['sent' => 0, 'failed' => 0, 'total' => 0];
        }

        $envioStmt = $conn->prepare('INSERT INTO doc_envios (documento_id, canal, `para`, payload, status, criado_por) VALUES (?, ?, ?, ?, ?, ?)');
        $sent = 0;
        $failed = 0;

        foreach ($destinatarios as $dest) {
            $email = $dest['email'];
            $nome = $dest['nome'] !== '' ? $dest['nome'] : 'destinatário';
            $payload = json_encode(['nome' => $nome, 'tipo' => $dest['tipo']], JSON_UNESCAPED_UNICODE);
            $canal = 'email';
            $status = 'pendente';
            $envioStmt->bind_param('issssi', $documentoId, $canal, $email, $payload, $status, $criadoPor);
            $envioStmt->execute();
            $envioId = (int)$envioStmt->insert_id;

            $boundary = 'b' . md5((string)microtime(true) . $email . $documentoId);
            $headers = [
                'MIME-Version: 1.0',
                'From: ' . $from,
                'Reply-To: ' . $from,
                'X-Mailer: PHP/' . phpversion(),
                'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            ];
            $headerString = implode("\r\n", $headers);

            $message = '<!doctype html><html lang="pt-BR"><body style="font-family:Arial,sans-serif;color:#111;">'
                . '<p>Olá, ' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '.</p>'
                . '<p>Segue o conteúdo do documento enviado pelo sistema SME.</p>'
                . '<p><strong>Tipo:</strong> ' . htmlspecialchars($tipoNome, ENT_QUOTES, 'UTF-8') . '<br>'
                . ($codigo !== '' ? '<strong>Número:</strong> ' . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') . '<br>' : '')
                . '<strong>Assunto:</strong> ' . htmlspecialchars($assuntoDocumento, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<hr>'
                . ($conteudoHtml !== ''
                    ? '<div>' . $conteudoHtml . '</div>'
                    : '<pre style="white-space:pre-wrap;">' . htmlspecialchars($conteudoTexto !== '' ? $conteudoTexto : 'Sem conteúdo.', ENT_QUOTES, 'UTF-8') . '</pre>')
                . '</body></html>';

            $body = "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $message . "\r\n\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: application/pdf; name=\"" . addslashes($pdf['filename']) . "\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=\"" . addslashes($pdf['filename']) . "\"\r\n\r\n"
                . chunk_split(base64_encode($pdf['content'])) . "\r\n"
                . "--{$boundary}--";

            if (@mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headerString)) {
                $sent++;
                $conn->query('UPDATE doc_envios SET status = "enviado", enviado_em = NOW() WHERE id = ' . $envioId);
            } else {
                $failed++;
                $conn->query('UPDATE doc_envios SET status = "falhou" WHERE id = ' . $envioId);
            }
        }

        $envioStmt->close();
        return ['sent' => $sent, 'failed' => $failed, 'total' => count($destinatarios)];
    }
}
