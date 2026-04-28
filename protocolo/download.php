<?php
ob_start();
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/signature_helpers.php';
require_once __DIR__ . '/govbr_signature_helpers.php';

require_login();
if (!user_can_access_system('protocolo') && !user_can_access_system('documentos')) {
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
$userIsAdmin = user_is_admin();

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

if ($userIsAdmin) {
    $allowed = true;
} else {
    $stmt = $conn->prepare('
        SELECT 1
        FROM doc_documentos d
        LEFT JOIN doc_destinatarios dd ON dd.documento_id = d.id
        LEFT JOIN doc_permissoes dp ON dp.documento_id = d.id
        LEFT JOIN doc_assinaturas da ON da.documento_id = d.id
        LEFT JOIN vinculo v ON v.matricula = ?
        WHERE d.id = ?
          AND (
            d.criado_por = ?
            OR dd.usuario_destino = ?
            OR dp.usuario = ?
            OR da.usuario = ?
            OR (dd.id_unidade_destino IS NOT NULL AND dd.id_unidade_destino = v.id_unidade)
          )
        LIMIT 1
    ');
    $stmt->bind_param('iiiiii', $matricula, $docId, $matricula, $matricula, $matricula, $matricula);
    $stmt->execute();
    $allowed = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
}

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

$assinaturasInfo = proto_fetch_signed_signatures($conn, $docId);
$validationUrl = proto_signature_validation_url();

$stmt = $conn->prepare('SELECT nome_arquivo, mime_type, tamanho_bytes, caminho_storage FROM doc_anexos WHERE documento_id = ? ORDER BY enviado_em DESC');
$stmt->bind_param('i', $docId);
$stmt->execute();
$anexos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
$pdfBinary = $dompdf->output();

$tipoNome = $documento['tipo_nome'] ?? 'documento';
$codigo = $numeracao['codigo_formatado'] ?? 'sem-numero';
$safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($tipoNome . '-' . $codigo));
$baseFilename = trim($safe, '-') !== '' ? trim($safe, '-') : ('documento-' . $docId);
$pdfFilename = $baseFilename . '.pdf';

$anexosExistentes = [];
$baseDir = realpath(__DIR__ . '/../uploads/docs');
foreach ($anexos as $anexo) {
    $relativePath = trim((string)($anexo['caminho_storage'] ?? ''));
    if ($relativePath === '') {
        continue;
    }
    $fullPath = realpath(__DIR__ . '/../' . $relativePath);
    if ($fullPath === false || $baseDir === false) {
        continue;
    }
    if (strncmp($fullPath, $baseDir, strlen($baseDir)) !== 0 || !is_file($fullPath)) {
        continue;
    }
    $anexosExistentes[] = [
        'nome_arquivo' => (string)($anexo['nome_arquivo'] ?? basename($fullPath)),
        'full_path' => $fullPath,
    ];
}

$assinaturasArquivos = [];
if (proto_govbr_schema_ready($conn)) {
    $stmt = $conn->prepare('SELECT arquivo_assinatura, assinatura_mime FROM doc_assinaturas WHERE documento_id = ? AND status = "assinado" AND provedor = "govbr" AND arquivo_assinatura IS NOT NULL AND arquivo_assinatura <> "" ORDER BY ordem');
    $stmt->bind_param('i', $docId);
    $stmt->execute();
    $assinaturasGov = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($assinaturasGov as $index => $assinaturaGov) {
        $relativePath = trim((string)($assinaturaGov['arquivo_assinatura'] ?? ''));
        if ($relativePath === '') {
            continue;
        }
        $fullPath = realpath(__DIR__ . '/../' . $relativePath);
        if ($fullPath === false || $baseDir === false) {
            continue;
        }
        if (strncmp($fullPath, $baseDir, strlen($baseDir)) !== 0 || !is_file($fullPath)) {
            continue;
        }
        $assinaturasArquivos[] = [
            'entry_name' => 'assinaturas/' . basename($fullPath) ?: ('assinatura-' . ($index + 1) . '.p7s'),
            'full_path' => $fullPath,
        ];
    }
}

if ($anexosExistentes === [] && $assinaturasArquivos === []) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
    echo $pdfBinary;
    exit;
}

if (!class_exists('ZipArchive')) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
    echo $pdfBinary;
    exit;
}

$zipPath = tempnam(sys_get_temp_dir(), 'doc_zip_');
if ($zipPath === false) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
    echo $pdfBinary;
    exit;
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
    @unlink($zipPath);
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
    echo $pdfBinary;
    exit;
}

$zip->addFromString($pdfFilename, $pdfBinary);
foreach ($anexosExistentes as $index => $anexo) {
    $entryName = 'anexos/' . preg_replace('/[\\\\\\/]+/', '-', $anexo['nome_arquivo']);
    if ($entryName === 'anexos/' || $entryName === 'anexos/-') {
        $entryName = 'anexos/anexo-' . ($index + 1);
    }
    $zip->addFile($anexo['full_path'], $entryName);
}
foreach ($assinaturasArquivos as $assinaturaArquivo) {
    $zip->addFile($assinaturaArquivo['full_path'], $assinaturaArquivo['entry_name']);
}
$zip->close();

if (ob_get_length()) {
    ob_end_clean();
}
header('Content-Type: application/zip');
header('Content-Length: ' . (string)filesize($zipPath));
header('Content-Disposition: attachment; filename="' . $baseFilename . '.zip"');
readfile($zipPath);
@unlink($zipPath);
exit;
