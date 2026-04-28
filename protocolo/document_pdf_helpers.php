<?php

require_once __DIR__ . '/signature_helpers.php';

if (!function_exists('proto_build_document_pdf')) {
    function proto_build_document_pdf(mysqli $conn, int $docId): array
    {
        if ($docId <= 0) {
            throw new RuntimeException('Documento inválido.');
        }

        $stmt = $conn->prepare('
            SELECT d.*, t.nome AS tipo_nome, uo.nome AS unidade_origem_nome
            FROM doc_documentos d
            INNER JOIN doc_tipos t ON t.id = d.tipo_id
            LEFT JOIN unidade uo ON uo.id_unidade = d.id_unidade_origem
            WHERE d.id = ?
        ');
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $documento = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$documento) {
            throw new RuntimeException('Documento não encontrado.');
        }

        $stmt = $conn->prepare('SELECT * FROM doc_versoes WHERE documento_id = ? ORDER BY numero_versao DESC LIMIT 1');
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $versao = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare('SELECT codigo_formatado, numero, ano FROM doc_numeracao WHERE documento_id = ? LIMIT 1');
        $stmt->bind_param('i', $docId);
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
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $destResult = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $destinatarios = [];
        foreach ($destResult as $dest) {
            $prefixo = trim((string)($dest['pronome_tratamento'] ?? ''));
            $prefixo = $prefixo !== '' ? $prefixo . ' – ' : '';
            if (($dest['tipo_destino'] ?? '') === 'interno') {
                if (!empty($dest['usuario_destino'])) {
                    $destinatarios[] = $prefixo . ($dest['usuario_nome'] ?: ('Usuário #' . (int)$dest['usuario_destino']));
                } elseif (!empty($dest['id_unidade_destino'])) {
                    $destinatarios[] = $prefixo . ($dest['unidade_nome'] ?: ('Unidade #' . (int)$dest['id_unidade_destino']));
                }
                continue;
            }
            $nome = trim((string)($dest['nome_externo'] ?? ''));
            $orgao = trim((string)($dest['orgao_externo'] ?? ''));
            $destinatarios[] = $prefixo . ($orgao !== '' ? ($nome . ' - ' . $orgao) : $nome);
        }
        $destinatarios = array_values(array_filter(array_unique($destinatarios)));

        $assinaturasInfo = proto_fetch_signed_signatures($conn, $docId);
        $validationUrl = proto_signature_validation_url();

        $unidadeOrigem = $documento['unidade_origem_nome'] ?? '';
        $logoFile = __DIR__ . '/../img/brasao-santo-augusto.png';
        $logoSrc = '';
        if (file_exists($logoFile)) {
            $imageData = base64_encode((string)file_get_contents($logoFile));
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
        $filename = trim((string)$safe, '-');
        if ($filename === '') {
            $filename = 'documento-' . $docId;
        }

        return [
            'html' => $html,
            'pdf_binary' => $dompdf->output(),
            'filename' => $filename . '.pdf',
            'documento' => $documento,
            'versao' => $versao,
            'numeracao' => $numeracao,
        ];
    }
}
