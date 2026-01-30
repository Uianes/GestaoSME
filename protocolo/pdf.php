<?php
ob_start();
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../config/db.php';

require_login();
if (!user_can_access_system('protocolo')) {
    http_response_code(403);
    echo 'Sem permissão de acesso.';
    exit;
}

$docId = (int)($_GET['doc'] ?? 0);
if ($docId <= 0) {
    http_response_code(400);
    echo 'Documento inválido.';
    exit;
}

$conn = db();
$matricula = (int)($_SESSION['user']['matricula'] ?? 0);

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
    http_response_code(404);
    echo 'Documento não encontrado.';
    exit;
}

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
$stmt->bind_param('iiiii', $matricula, $docId, $matricula, $matricula, $matricula);
$stmt->execute();
$allowed = (bool)$stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$allowed) {
    http_response_code(403);
    echo 'Sem permissão para visualizar este documento.';
    exit;
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
    if ($dest['tipo_destino'] === 'interno') {
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

$stmt = $conn->prepare('
    SELECT a.assinado_em, u.nome, v.cargo
    FROM doc_assinaturas a
    INNER JOIN usuarios u ON u.matricula = a.usuario
    LEFT JOIN vinculo v ON v.matricula = a.usuario
    WHERE a.documento_id = ? AND a.status = "assinado"
    ORDER BY a.assinado_em DESC
    LIMIT 1
');
$stmt->bind_param('i', $docId);
$stmt->execute();
$assinatura = $stmt->get_result()->fetch_assoc();
$stmt->close();

$assinaturaInfo = [];
if ($assinatura) {
    $assinaturaInfo = [
        'nome' => $assinatura['nome'] ?? '',
        'cargo' => $assinatura['cargo'] ?? '',
        'data' => $assinatura['assinado_em'] ? date('d/m/Y H:i', strtotime($assinatura['assinado_em'])) : '',
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

if (ob_get_length()) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
echo $dompdf->output();
