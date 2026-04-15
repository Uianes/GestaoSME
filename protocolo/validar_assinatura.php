<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/signature_helpers.php';

$conn = db();
$codigo = trim((string)($_REQUEST['codigo'] ?? ''));
$resultado = null;
$mensagemErro = null;

if ($codigo !== '') {
    $assinaturaId = proto_signature_id_from_code($codigo);
    if ($assinaturaId <= 0) {
        $mensagemErro = 'Assinatura inválida.';
    } else {
        $stmt = $conn->prepare('
            SELECT
                a.id,
                a.assinado_em,
                u.nome AS assinante_nome,
                v.cargo AS assinante_cargo,
                d.id AS documento_id,
                d.assunto,
                t.nome AS tipo_nome,
                n.codigo_formatado
            FROM doc_assinaturas a
            INNER JOIN doc_documentos d ON d.id = a.documento_id
            INNER JOIN doc_tipos t ON t.id = d.tipo_id
            INNER JOIN usuarios u ON u.matricula = a.usuario
            LEFT JOIN doc_numeracao n ON n.documento_id = d.id
            LEFT JOIN (
                SELECT matricula, MAX(cargo) AS cargo
                FROM vinculo
                GROUP BY matricula
            ) v ON v.matricula = a.usuario
            WHERE a.id = ? AND a.status = "assinado"
            LIMIT 1
        ');
        $stmt->bind_param('i', $assinaturaId);
        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        if (!$resultado) {
            $mensagemErro = 'Assinatura inválida.';
        }
    }
}

function h_validar_assinatura($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validação de assinatura</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
</head>
<body class="bg-light">
    <div class="container py-5" style="max-width: 860px;">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h2 class="mb-2">Validação de assinatura digital</h2>
                <p class="text-muted mb-4">Informe o código de verificação da assinatura para confirmar a autenticidade do documento.</p>

                <form method="get" class="row g-3 mb-4">
                    <div class="col-12 col-md-8">
                        <label class="form-label" for="codigo">Código de verificação</label>
                        <input
                            type="text"
                            class="form-control"
                            id="codigo"
                            name="codigo"
                            value="<?= h_validar_assinatura($codigo) ?>"
                            placeholder="ASS-000123"
                            autocomplete="off"
                        >
                    </div>
                    <div class="col-12 col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Validar assinatura</button>
                    </div>
                </form>

                <?php if ($mensagemErro !== null): ?>
                    <div class="alert alert-danger mb-0"><?= h_validar_assinatura($mensagemErro) ?></div>
                <?php elseif ($resultado !== null): ?>
                    <div class="alert alert-success">
                        Assinatura válida.
                    </div>
                    <div class="border rounded p-4 bg-body-tertiary">
                        <div class="mb-2">
                            Documento
                            <strong><?= h_validar_assinatura(($resultado['tipo_nome'] ?? 'Documento') . (!empty($resultado['codigo_formatado']) ? ' ' . $resultado['codigo_formatado'] : '')) ?></strong>:
                            <?= h_validar_assinatura($resultado['assunto'] ?? '') ?>.
                        </div>
                        <div class="mb-2">
                            Assinado digitalmente em
                            <strong><?= h_validar_assinatura(!empty($resultado['assinado_em']) ? date('d/m/Y H:i', strtotime((string)$resultado['assinado_em'])) : '-') ?></strong>.
                        </div>
                        <div class="mb-2">
                            Por
                            <strong><?= h_validar_assinatura($resultado['assinante_nome'] ?? '') ?></strong>
                            <?php if (!empty($resultado['assinante_cargo'])): ?>
                                (<?= h_validar_assinatura($resultado['assinante_cargo']) ?>)
                            <?php endif; ?>.
                        </div>
                        <div>
                            Código de verificação:
                            <strong><?= h_validar_assinatura(proto_signature_verification_code((int)($resultado['id'] ?? 0))) ?></strong>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
