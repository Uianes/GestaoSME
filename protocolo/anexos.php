<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../config/db.php';

require_login();
if (!user_can_access_system('protocolo') && !user_can_access_system('documentos')) {
    http_response_code(403);
    echo 'Sem permissão de acesso.';
    exit;
}

$docId = (int)($_GET['doc'] ?? 0);
$autoStart = isset($_GET['auto']) && (string)$_GET['auto'] === '1';
if ($docId <= 0) {
    http_response_code(400);
    echo 'Documento inválido.';
    exit;
}

$conn = db();
$matricula = (int)($_SESSION['user']['matricula'] ?? 0);
$userIsAdmin = user_is_admin();

$stmt = $conn->prepare('
    SELECT d.id, d.assunto
    FROM doc_documentos d
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

$stmt = $conn->prepare('SELECT nome_arquivo, mime_type, tamanho_bytes, caminho_storage FROM doc_anexos WHERE documento_id = ? ORDER BY enviado_em DESC');
$stmt->bind_param('i', $docId);
$stmt->execute();
$anexos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function h_proto_anexo($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anexos do documento</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h3 class="mb-1">Anexos do documento</h3>
                <div class="text-muted"><?= h_proto_anexo($documento['assunto'] ?? ('Documento #' . $docId)) ?></div>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary" href="pdf.php?doc=<?= (int)$docId ?>" target="_blank">Baixar PDF</a>
                <a class="btn btn-outline-secondary" href="../app.php?page=documentos&doc=<?= (int)$docId ?>">Voltar</a>
            </div>
        </div>

        <?php if ($anexos === []): ?>
            <div class="alert alert-secondary mb-0">Este documento não possui anexos.</div>
        <?php else: ?>
            <?php if ($autoStart): ?>
                <div class="alert alert-info">
                    O sistema está tentando iniciar o download automático dos anexos. Se o navegador bloquear múltiplos downloads, use os botões individuais abaixo.
                </div>
            <?php endif; ?>
            <div class="list-group shadow-sm">
                <?php foreach ($anexos as $anexo): ?>
                    <?php $downloadUrl = '../' . ltrim((string)($anexo['caminho_storage'] ?? ''), '/'); ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div>
                            <div class="fw-semibold"><?= h_proto_anexo($anexo['nome_arquivo'] ?? 'Arquivo') ?></div>
                            <div class="small text-muted">
                                <?= h_proto_anexo($anexo['mime_type'] ?? 'arquivo') ?>
                                • <?= number_format((int)($anexo['tamanho_bytes'] ?? 0) / 1024, 1, ',', '.') ?> KB
                            </div>
                        </div>
                        <a
                            class="btn btn-sm btn-primary"
                            href="<?= h_proto_anexo($downloadUrl) ?>"
                            target="_blank"
                            download
                            data-auto-download
                        >Baixar anexo</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($autoStart && $anexos !== []): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const links = Array.from(document.querySelectorAll('[data-auto-download]'));
                links.forEach(function (link, index) {
                    window.setTimeout(function () {
                        const iframe = document.createElement('iframe');
                        iframe.style.display = 'none';
                        iframe.src = link.href;
                        document.body.appendChild(iframe);
                    }, index * 900);
                });
            });
        </script>
    <?php endif; ?>
</body>
</html>
