<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../config/db.php';

require_login();
if (!user_can_access_system('protocolo')) {
    ?>
    <!doctype html>
    <html lang="pt-br">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
      <title>Protocolo Eletrônico</title>
    </head>
    <body class="bg-light">
      <div class="container py-4">
        <div class="alert alert-danger" role="alert">Sem permissão de acesso.</div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$conn = db();
$matricula = (int)($_SESSION['user']['matricula'] ?? 0);
$docId = (int)($_GET['doc'] ?? 0);

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$tipos = [];
$result = $conn->query('SELECT id, nome FROM doc_tipos WHERE ativo = 1 ORDER BY nome');
while ($row = $result->fetch_assoc()) {
    $tipos[] = $row;
}

$unidades = [];
$result = $conn->query('SELECT id_unidade, nome FROM unidade ORDER BY nome');
while ($row = $result->fetch_assoc()) {
    $unidades[] = $row;
}

$usuarios = [];
$result = $conn->query('SELECT matricula, nome FROM usuarios WHERE ativo = 1 ORDER BY nome');
while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$documento = null;
$destinatarios = [];
$versao = null;
$numeracao = null;
$anexos = [];
$assinaturas = [];
$auditoria = [];
$isModeloOficial = false;
if ($docId > 0) {
    $stmt = $conn->prepare('
        SELECT d.*, s.nome AS status_nome, t.nome AS tipo_nome
        FROM doc_documentos d
        INNER JOIN doc_status s ON s.id = d.status_id
        INNER JOIN doc_tipos t ON t.id = d.tipo_id
        WHERE d.id = ?
    ');
    $stmt->bind_param('i', $docId);
    $stmt->execute();
    $documento = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($documento) {
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
            $documento = null;
        }
    }

    if ($documento) {
        $tipoLower = strtolower((string)($documento['tipo_nome'] ?? ''));
        $isModeloOficial = strpos($tipoLower, 'memorando') !== false
            || strpos($tipoLower, 'oficio') !== false
            || strpos($tipoLower, 'ofício') !== false;

        $stmt = $conn->prepare('SELECT * FROM doc_destinatarios WHERE documento_id = ? ORDER BY ordem');
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $destinatarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare('SELECT * FROM doc_versoes WHERE documento_id = ? ORDER BY numero_versao DESC LIMIT 1');
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $versao = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare('SELECT codigo_formatado FROM doc_numeracao WHERE documento_id = ? LIMIT 1');
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $numeracao = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare('SELECT * FROM doc_anexos WHERE documento_id = ? ORDER BY enviado_em DESC');
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $anexos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare('
            SELECT a.*, u.nome
            FROM doc_assinaturas a
            INNER JOIN usuarios u ON u.matricula = a.usuario
            WHERE a.documento_id = ?
            ORDER BY a.ordem
        ');
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $assinaturas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare('
            SELECT a.*, u.nome AS usuario_nome
            FROM doc_auditoria a
            LEFT JOIN usuarios u ON u.matricula = a.usuario
            WHERE a.entidade = "documento" AND a.entidade_id = ?
            ORDER BY a.criado_em DESC
        ');
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $auditoria = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$docs = [];
$stmt = $conn->prepare('
    SELECT DISTINCT d.id, d.assunto, d.criado_em, s.nome AS status_nome, t.nome AS tipo_nome, n.codigo_formatado
    FROM doc_documentos d
    INNER JOIN doc_status s ON s.id = d.status_id
    INNER JOIN doc_tipos t ON t.id = d.tipo_id
    LEFT JOIN doc_numeracao n ON n.documento_id = d.id
    LEFT JOIN doc_destinatarios dd ON dd.documento_id = d.id
    LEFT JOIN doc_permissoes dp ON dp.documento_id = d.id
    LEFT JOIN vinculo v ON v.matricula = ?
    WHERE d.criado_por = ?
       OR dd.usuario_destino = ?
       OR dp.usuario = ?
       OR (dd.id_unidade_destino IS NOT NULL AND dd.id_unidade_destino = v.id_unidade)
    ORDER BY d.criado_em DESC
    LIMIT 200
');
$stmt->bind_param('iiii', $matricula, $matricula, $matricula, $matricula);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $docs[] = $row;
}
$stmt->close();

$statusClasses = [
    'rascunho' => 'secondary',
    'revisao' => 'info',
    'assinatura' => 'warning',
    'assinado' => 'primary',
    'enviado' => 'success',
    'arquivado' => 'dark',
    'cancelado' => 'danger'
];
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Protocolo Eletrônico</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
  <style>
    body { background: #f6f8fb; }
    .card { border-radius: 14px; }
    .badge-status { text-transform: capitalize; }
    .doc-content table { width: 100%; border-collapse: collapse; }
    .doc-content th, .doc-content td { border: 1px solid #cbd5e1; padding: 6px 8px; }
    .doc-content th { background: #f8fafc; }
    .doc-content img { max-width: 100%; height: auto; }
    .doc-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .doc-actions .badge { white-space: nowrap; }
    .dest-row { display: grid; grid-template-columns: 1fr; gap: 8px; }
    .dest-row select { width: 100%; }
    @media (min-width: 768px) {
      .dest-row { grid-template-columns: 1fr 180px; align-items: center; }
    }
    .tox .tox-toolbar__primary { flex-wrap: wrap; }
    .tox .tox-tbtn { min-width: 32px; }
    @media (max-width: 767.98px) {
      .container-fluid { padding-left: 16px; padding-right: 16px; }
      .doc-actions { width: 100%; justify-content: flex-start; }
      .doc-actions .btn { width: 100%; }
      .doc-actions .badge { order: -1; }
      .btn-new-doc { width: 100%; }
      .card-body { padding: 1rem; }
      .modal-dialog { margin: 0.5rem; }
      .modal-body { padding: 1rem; }
      .tab-content { padding: 1rem; }
    }
  </style>
</head>
<body>
  <div class="container-fluid py-4 px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
      <div>
        <h2 class="mb-1">Protocolo Eletrônico</h2>
        <div class="text-muted">Crie, registre e encaminhe documentos oficiais.</div>
      </div>
      <button class="btn btn-primary btn-new-doc" data-bs-toggle="modal" data-bs-target="#docModal">
        <i class="bi bi-file-earmark-plus me-2"></i>Novo documento
      </button>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="alert alert-success"><?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger"><?= h($flashError) ?></div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-12 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="mb-3">Seus documentos</h5>
            <?php if (empty($docs)): ?>
              <div class="text-muted">Nenhum documento encontrado.</div>
            <?php else: ?>
              <div class="list-group">
                <?php foreach ($docs as $doc): ?>
                  <a class="list-group-item list-group-item-action" href="index.php?doc=<?= (int)$doc['id'] ?>">
                    <div class="d-flex justify-content-between">
                      <div>
                        <div class="fw-semibold"><?= h($doc['assunto']) ?></div>
                        <div class="text-muted small">
                          <?= h($doc['tipo_nome']) ?>
                          <?php if (!empty($doc['codigo_formatado'])): ?>
                            • <?= h($doc['codigo_formatado']) ?>
                          <?php endif; ?>
                          • <?= date('d/m/Y H:i', strtotime($doc['criado_em'])) ?>
                        </div>
                      </div>
                      <?php $statusKey = strtolower((string)$doc['status_nome']); ?>
                      <span class="badge text-bg-<?= $statusClasses[$statusKey] ?? 'light' ?> badge-status"><?= h($doc['status_nome']) ?></span>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-7">
        <div class="card shadow-sm">
          <div class="card-body">
            <?php if (!$documento): ?>
              <div class="text-muted">Selecione um documento para visualizar os detalhes.</div>
            <?php else: ?>
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                  <h5 class="mb-1"><?= h($documento['assunto']) ?></h5>
                  <div class="text-muted small"><?= h($documento['tipo_nome']) ?> • <?= h($documento['status_nome']) ?></div>
                </div>
                <div class="doc-actions">
                  <?php if ($numeracao): ?>
                    <span class="badge text-bg-primary"><?= h($numeracao['codigo_formatado']) ?></span>
                  <?php endif; ?>
                  <?php if ($isModeloOficial): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="print.php?doc=<?= (int)$documento['id'] ?>" target="_blank">Imprimir</a>
                    <a class="btn btn-sm btn-outline-primary" href="pdf.php?doc=<?= (int)$documento['id'] ?>" target="_blank">Baixar PDF</a>
                  <?php endif; ?>
                  <?php if ((int)$documento['criado_por'] === $matricula): ?>
                    <button
                      class="btn btn-sm btn-outline-warning"
                      type="button"
                      data-bs-toggle="modal"
                      data-bs-target="#editModal"
                      data-id="<?= (int)$documento['id'] ?>"
                      data-assunto="<?= h($documento['assunto']) ?>"
                      data-confidencial="<?= (int)$documento['confidencial'] ?>"
                      data-conteudo="<?= h(base64_encode($versao['conteudo'] ?? '')) ?>"
                      data-destusuarios="<?= h(base64_encode(json_encode(array_values(array_filter(array_map(fn($d) => $d['tipo_destino']==='interno' && $d['usuario_destino'] ? (int)$d['usuario_destino'] : null, $destinatarios))))) ?>"
                      data-destunidades="<?= h(base64_encode(json_encode(array_values(array_filter(array_map(fn($d) => $d['tipo_destino']==='interno' && $d['id_unidade_destino'] ? (int)$d['id_unidade_destino'] : null, $destinatarios))))) ?>"
                      data-destusuariospronome="<?= h(base64_encode(json_encode(array_filter(array_map(fn($d) => $d['tipo_destino']==='interno' && $d['usuario_destino'] ? ['id'=>(int)$d['usuario_destino'],'pronome'=>$d['pronome_tratamento']??''] : null, $destinatarios))))) ?>"
                      data-destunidadespronome="<?= h(base64_encode(json_encode(array_filter(array_map(fn($d) => $d['tipo_destino']==='interno' && $d['id_unidade_destino'] ? ['id'=>(int)$d['id_unidade_destino'],'pronome'=>$d['pronome_tratamento']??''] : null, $destinatarios))))) ?>"
                      data-destexternos="<?= h(base64_encode(json_encode(array_values(array_filter(array_map(fn($d) => $d['tipo_destino']==='externo' ? ['nome'=>$d['nome_externo']??'','orgao'=>$d['orgao_externo']??'','email'=>$d['email_externo']??'','endereco'=>$d['endereco_externo']??'','pronome'=>$d['pronome_tratamento']??''] : null, $destinatarios))))) ?>"
                      data-signusuarios="<?= h(base64_encode(json_encode(array_map(fn($a)=> (int)$a['usuario'], $assinaturas)))) ?>"
                    >Editar</button>
                  <?php endif; ?>
                </div>
              </div>

              <div class="mb-3">
                <div class="text-muted small">Conteúdo</div>
                <div class="border rounded p-3 bg-white doc-content">
                  <?= $versao ? $versao['conteudo'] : '<span class="text-muted">Sem conteúdo.</span>' ?>
                </div>
              </div>

              <div class="mb-3">
                <div class="text-muted small">Destinatários</div>
                <?php if (empty($destinatarios)): ?>
                  <div class="text-muted">Nenhum destinatário.</div>
                <?php else: ?>
                  <ul class="list-group list-group-flush">
                    <?php foreach ($destinatarios as $dest): ?>
                      <li class="list-group-item">
                        <?php $prefixo = $dest['pronome_tratamento'] ? h($dest['pronome_tratamento']) . ' – ' : ''; ?>
                        <?php if ($dest['tipo_destino'] === 'interno'): ?>
                          <?= $prefixo ?><?= $dest['usuario_destino'] ? 'Usuário #' . h($dest['usuario_destino']) : 'Unidade #' . h($dest['id_unidade_destino']) ?>
                        <?php else: ?>
                          <?= $prefixo ?><?= h($dest['nome_externo']) ?> (<?= h($dest['email_externo']) ?>)
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <div class="text-muted small">Assinaturas</div>
                <?php if (empty($assinaturas)): ?>
                  <div class="text-muted">Sem assinaturas configuradas.</div>
                <?php else: ?>
                  <ul class="list-group list-group-flush">
                    <?php foreach ($assinaturas as $ass): ?>
                      <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                          <div class="fw-semibold"><?= h($ass['nome']) ?></div>
                          <div class="text-muted small">Ordem <?= (int)$ass['ordem'] ?></div>
                        </div>
                        <span class="badge text-bg-light border badge-status"><?= h($ass['status']) ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <div class="text-muted small">Histórico</div>
                <?php if (empty($auditoria)): ?>
                  <div class="text-muted">Nenhum registro.</div>
                <?php else: ?>
                  <ul class="list-group list-group-flush">
                    <?php foreach ($auditoria as $log): ?>
                      <li class="list-group-item">
                        <div class="fw-semibold"><?= h($log['evento']) ?></div>
                        <div class="text-muted small">
                          <?= h($log['usuario_nome'] ?? 'Sistema') ?> • <?= date('d/m/Y H:i', strtotime($log['criado_em'])) ?>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <div class="text-muted small">Anexos</div>
                <?php if (empty($anexos)): ?>
                  <div class="text-muted">Nenhum anexo.</div>
                <?php else: ?>
                  <ul class="list-group list-group-flush">
                    <?php foreach ($anexos as $anexo): ?>
                      <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                          <div class="fw-semibold"><?= h($anexo['nome_arquivo']) ?></div>
                          <div class="text-muted small"><?= h($anexo['mime_type']) ?> • <?= number_format((int)$anexo['tamanho_bytes'] / 1024, 1, ',', '.') ?> KB</div>
                        </div>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= h('../' . $anexo['caminho_storage']) ?>" target="_blank">Abrir</a>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>

              <?php if ((int)$documento['criado_por'] === $matricula): ?>
                <form class="mb-3" method="post" action="actions/document_upload.php" enctype="multipart/form-data">
                  <input type="hidden" name="documento_id" value="<?= (int)$documento['id'] ?>">
                  <div class="input-group">
                    <input class="form-control" type="file" name="anexo" required>
                    <button class="btn btn-outline-primary" type="submit">Enviar anexo</button>
                  </div>
                </form>
              <?php endif; ?>

              <?php if ((int)$documento['criado_por'] === $matricula && in_array((int)$documento['status_id'], [1, 4], true)): ?>
                <form method="post" action="actions/document_send.php">
                  <input type="hidden" name="documento_id" value="<?= (int)$documento['id'] ?>">
                  <button class="btn btn-success">Enviar documento</button>
                </form>
              <?php endif; ?>

              <?php if (!empty($assinaturas)): ?>
                <?php foreach ($assinaturas as $ass): ?>
                  <?php if ((int)$ass['usuario'] === $matricula && $ass['status'] === 'pendente'): ?>
                    <form class="mt-3" method="post" action="actions/document_sign.php">
                      <input type="hidden" name="documento_id" value="<?= (int)$documento['id'] ?>">
                      <button class="btn btn-outline-primary">Assinar documento</button>
                    </form>
                    <form class="mt-2" method="post" action="actions/document_reject.php">
                      <input type="hidden" name="documento_id" value="<?= (int)$documento['id'] ?>">
                      <input type="text" name="observacao" class="form-control mb-2" placeholder="Motivo da recusa (opcional)">
                      <button class="btn btn-outline-danger">Recusar assinatura</button>
                    </form>
                    <?php break; ?>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="docModal" tabindex="-1" aria-labelledby="docModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="docModalLabel">Novo documento</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <form method="post" action="actions/document_create.php" id="docForm">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Tipo</label>
                <select class="form-select" name="tipo_id" required>
                  <option value="">Selecione</option>
                  <?php foreach ($tipos as $tipo): ?>
                    <option value="<?= (int)$tipo['id'] ?>"><?= h($tipo['nome']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Unidade de origem</label>
                <select class="form-select" name="id_unidade_origem" required>
                  <option value="">Selecione</option>
                  <?php foreach ($unidades as $unidade): ?>
                    <option value="<?= (int)$unidade['id_unidade'] ?>"><?= h($unidade['nome']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Assunto</label>
                <input type="text" class="form-control" name="assunto" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Confidencial</label>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="confidencial" id="confidencial">
                  <label class="form-check-label" for="confidencial">Sim</label>
                </div>
              </div>
              <div class="col-12">
                <ul class="nav nav-tabs" id="docTabs" role="tablist">
                  <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="content-tab" data-bs-toggle="tab" data-bs-target="#content-pane" type="button" role="tab" aria-controls="content-pane" aria-selected="true">Conteúdo</button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="dest-tab" data-bs-toggle="tab" data-bs-target="#dest-pane" type="button" role="tab" aria-controls="dest-pane" aria-selected="false">Destinatários</button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sign-tab" data-bs-toggle="tab" data-bs-target="#sign-pane" type="button" role="tab" aria-controls="sign-pane" aria-selected="false">Assinaturas</button>
                  </li>
                </ul>
                <div class="tab-content border border-top-0 rounded-bottom p-3">
                  <div class="tab-pane fade show active" id="content-pane" role="tabpanel" aria-labelledby="content-tab">
                    <label class="form-label">Conteúdo</label>
                    <div class="mb-2">
                      <label class="form-label">Modelo do memorando</label>
                      <select class="form-select" id="memorandoModelo">
                        <option value="">Sem modelo</option>
                        <option value="contratacao">Memorando modelo para contratação</option>
                        <option value="nomeacao">Memorando modelo para nomeação</option>
                        <option value="impacto">Memorando modelo para pedido de impacto</option>
                        <option value="folha">Memorando modelo de pagamento em folha</option>
                      </select>
                    </div>
                    <textarea id="docConteudo" name="conteudo"></textarea>
                  </div>
                  <div class="tab-pane fade" id="dest-pane" role="tabpanel" aria-labelledby="dest-tab">
                    <div class="mb-3">
                      <label class="form-label">Pesquisar destinatários</label>
                      <input type="text" class="form-control" id="searchDestinatarios" placeholder="Buscar usuário ou unidade">
                    </div>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label">Usuários internos</label>
                        <div class="border rounded p-2" style="max-height: 240px; overflow:auto;">
                          <?php foreach ($usuarios as $usuario): ?>
                            <div class="dest-item dest-row" data-label="<?= h($usuario['nome']) ?>">
                              <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="dest_usuarios[]" value="<?= (int)$usuario['matricula'] ?>" id="user-<?= (int)$usuario['matricula'] ?>">
                                <label class="form-check-label" for="user-<?= (int)$usuario['matricula'] ?>">
                                  <?= h($usuario['nome']) ?>
                                </label>
                              </div>
                              <select class="form-select form-select-sm" name="dest_usuarios_pronome[<?= (int)$usuario['matricula'] ?>]">
                                <option value="">Sem tratamento</option>
                                <option value="À Sra Prefeita Municipal">À Sra Prefeita Municipal</option>
                                <option value="Ao Sr Prefeito Municipal">Ao Sr Prefeito Municipal</option>
                                <option value="À Sra Secretária Municipal">À Sra Secretária Municipal</option>
                                <option value="Ao Sr Secretário Municipal">Ao Sr Secretário Municipal</option>
                                <option value="À Sra Diretora">À Sra Diretora</option>
                                <option value="Ao Sr Diretor">Ao Sr Diretor</option>
                                <option value="À Sra Coordenadora">À Sra Coordenadora</option>
                                <option value="Ao Sr Coordenador">Ao Sr Coordenador</option>
                                <option value="À Sra Presidente">À Sra Presidente</option>
                                <option value="Ao Sr Presidente">Ao Sr Presidente</option>
                                <option value="À Sra">À Sra</option>
                                <option value="Ao Sr">Ao Sr</option>
                              </select>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Unidades</label>
                        <div class="border rounded p-2" style="max-height: 240px; overflow:auto;">
                          <?php foreach ($unidades as $unidade): ?>
                            <div class="dest-item dest-row" data-label="<?= h($unidade['nome']) ?>">
                              <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="dest_unidades[]" value="<?= (int)$unidade['id_unidade'] ?>" id="unit-<?= (int)$unidade['id_unidade'] ?>">
                                <label class="form-check-label" for="unit-<?= (int)$unidade['id_unidade'] ?>">
                                  <?= h($unidade['nome']) ?>
                                </label>
                              </div>
                              <select class="form-select form-select-sm" name="dest_unidades_pronome[<?= (int)$unidade['id_unidade'] ?>]">
                                <option value="">Sem tratamento</option>
                                <option value="À Unidade">À Unidade</option>
                                <option value="Ao Setor">Ao Setor</option>
                                <option value="À Coordenação">À Coordenação</option>
                                <option value="À Direção">À Direção</option>
                                <option value="À Secretaria">À Secretaria</option>
                              </select>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <div class="col-12">
                        <label class="form-label">Destinatários externos</label>
                        <div id="externos"></div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="addExterno">Adicionar destinatário externo</button>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade" id="sign-pane" role="tabpanel" aria-labelledby="sign-tab">
                    <div class="mb-3">
                      <label class="form-label">Pesquisar assinantes</label>
                      <input type="text" class="form-control" id="searchAssinaturas" placeholder="Buscar usuário">
                    </div>
                    <div class="row g-3">
                      <div class="col-md-7">
                        <label class="form-label">Usuários disponíveis</label>
                        <div class="border rounded p-2" style="max-height: 240px; overflow:auto;">
                          <?php foreach ($usuarios as $usuario): ?>
                            <div class="form-check sign-item" data-label="<?= h($usuario['nome']) ?>">
                              <input class="form-check-input sign-checkbox" type="checkbox" value="<?= (int)$usuario['matricula'] ?>" data-label="<?= h($usuario['nome']) ?>" id="sign-<?= (int)$usuario['matricula'] ?>">
                              <label class="form-check-label" for="sign-<?= (int)$usuario['matricula'] ?>">
                                <?= h($usuario['nome']) ?>
                              </label>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <div class="col-md-5">
                        <label class="form-label">Assinaturas (ordem)</label>
                        <ol class="list-group list-group-numbered mb-2" id="signOrderList"></ol>
                        <div class="form-text">A ordem segue a seleção acima.</div>
                        <div id="signOrderInputs"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Salvar rascunho</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel">Editar documento</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <form method="post" action="actions/document_edit.php" id="editForm">
          <div class="modal-body">
            <input type="hidden" name="documento_id" id="editDocumentoId">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Assunto</label>
                <input type="text" class="form-control" name="assunto" id="editAssunto" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Confidencial</label>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="confidencial" id="editConfidencial">
                  <label class="form-check-label" for="editConfidencial">Sim</label>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Conteúdo</label>
                <textarea id="docConteudoEdit" name="conteudo"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Destinatários (usuários)</label>
                <div class="border rounded p-2" style="max-height: 200px; overflow:auto;">
                  <?php foreach ($usuarios as $usuario): ?>
                    <div class="dest-item dest-row" data-label="<?= h($usuario['nome']) ?>">
                      <div class="form-check">
                        <input class="form-check-input edit-dest-user" type="checkbox" name="dest_usuarios[]" value="<?= (int)$usuario['matricula'] ?>" id="edit-user-<?= (int)$usuario['matricula'] ?>">
                        <label class="form-check-label" for="edit-user-<?= (int)$usuario['matricula'] ?>">
                          <?= h($usuario['nome']) ?>
                        </label>
                      </div>
                      <select class="form-select form-select-sm edit-dest-user-pronome" name="dest_usuarios_pronome[<?= (int)$usuario['matricula'] ?>]">
                        <option value="">Sem tratamento</option>
                        <option value="À Sra Prefeita Municipal">À Sra Prefeita Municipal</option>
                        <option value="Ao Sr Prefeito Municipal">Ao Sr Prefeito Municipal</option>
                        <option value="À Sra Secretária Municipal">À Sra Secretária Municipal</option>
                        <option value="Ao Sr Secretário Municipal">Ao Sr Secretário Municipal</option>
                        <option value="À Sra Diretora">À Sra Diretora</option>
                        <option value="Ao Sr Diretor">Ao Sr Diretor</option>
                        <option value="À Sra Coordenadora">À Sra Coordenadora</option>
                        <option value="Ao Sr Coordenador">Ao Sr Coordenador</option>
                        <option value="À Sra Presidente">À Sra Presidente</option>
                        <option value="Ao Sr Presidente">Ao Sr Presidente</option>
                        <option value="À Sra">À Sra</option>
                        <option value="Ao Sr">Ao Sr</option>
                      </select>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Destinatários (unidades)</label>
                <div class="border rounded p-2" style="max-height: 200px; overflow:auto;">
                  <?php foreach ($unidades as $unidade): ?>
                    <div class="dest-item dest-row" data-label="<?= h($unidade['nome']) ?>">
                      <div class="form-check">
                        <input class="form-check-input edit-dest-unit" type="checkbox" name="dest_unidades[]" value="<?= (int)$unidade['id_unidade'] ?>" id="edit-unit-<?= (int)$unidade['id_unidade'] ?>">
                        <label class="form-check-label" for="edit-unit-<?= (int)$unidade['id_unidade'] ?>">
                          <?= h($unidade['nome']) ?>
                        </label>
                      </div>
                      <select class="form-select form-select-sm edit-dest-unit-pronome" name="dest_unidades_pronome[<?= (int)$unidade['id_unidade'] ?>]">
                        <option value="">Sem tratamento</option>
                        <option value="À Unidade">À Unidade</option>
                        <option value="Ao Setor">Ao Setor</option>
                        <option value="À Coordenação">À Coordenação</option>
                        <option value="À Direção">À Direção</option>
                        <option value="À Secretaria">À Secretaria</option>
                      </select>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Destinatários externos</label>
                <div id="externosEdit"></div>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="addExternoEdit">Adicionar destinatário externo</button>
              </div>
              <div class="col-12">
                <label class="form-label">Assinaturas (ordem)</label>
                <div class="border rounded p-2" style="max-height: 200px; overflow:auto;">
                  <?php foreach ($usuarios as $usuario): ?>
                    <div class="form-check">
                      <input class="form-check-input edit-sign" type="checkbox" value="<?= (int)$usuario['matricula'] ?>" data-label="<?= h($usuario['nome']) ?>" id="edit-sign-<?= (int)$usuario['matricula'] ?>">
                      <label class="form-check-label" for="edit-sign-<?= (int)$usuario['matricula'] ?>">
                        <?= h($usuario['nome']) ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="mt-2">
                  <label class="form-label">Ordem das assinaturas (arraste para reordenar)</label>
                  <ol class="list-group list-group-numbered" id="editSignOrderList"></ol>
                  <div id="editSignOrderInputs"></div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Salvar alterações</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script>
    tinymce.init({
      selector: '#docConteudo, #docConteudoEdit',
      height: 360,
      menubar: false,
      branding: false,
      plugins: 'lists link image table code',
      toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright | bullist numlist | table | link image | code',
      toolbar_mode: 'wrap',
      toolbar_groups: {
        format: { icon: 'bold', tooltip: 'Formatação' }
      },
      toolbar1: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright',
      toolbar2: 'bullist numlist | table | link image | code',
      statusbar: false,
      image_title: true,
      automatic_uploads: false,
      images_upload_handler: (blobInfo, progress) => new Promise((resolve) => {
        const base64 = 'data:' + blobInfo.blob().type + ';base64,' + blobInfo.base64();
        resolve(base64);
      }),
      content_style: 'body { font-family: "Segoe UI", sans-serif; font-size: 14px; } table { width: 100%; border-collapse: collapse; } td, th { border: 1px solid #94a3b8; padding: 6px 8px; }',
    });

    document.getElementById('docForm').addEventListener('submit', () => {
      if (window.tinymce) {
        tinymce.triggerSave();
      }
    });
    document.getElementById('editForm').addEventListener('submit', () => {
      if (window.tinymce) {
        tinymce.triggerSave();
      }
    });

    const modelosMemorando = {
      contratacao: `
        <p>Prezada,</p>
        <p>Na oportunidade em que a cumprimento muito cordialmente, venho por meio deste solicitar a contratação temporária conforme dados abaixo:</p>
        <table>
          <tr><th colspan="2">Dados necessários para a Contratação Temporária:</th></tr>
          <tr><td>Cargo</td><td></td></tr>
          <tr><td>Quantidade</td><td></td></tr>
          <tr><td>Carga horária</td><td></td></tr>
          <tr><td>Nº da Lei Autorizativa</td><td></td></tr>
          <tr><td>Nº do Projeto de Lei</td><td></td></tr>
          <tr><td>Nº do Impacto</td><td></td></tr>
          <tr><td>Nº do PSS ou Concurso</td><td></td></tr>
          <tr><td>Centro de Custo</td><td></td></tr>
          <tr><td>Local de trabalho</td><td></td></tr>
          <tr><td>Horário de trabalho</td><td></td></tr>
          <tr><td>Substituição/Demanda<br><small>Obs: Se for substituição informar o nome do servidor substituído.</small></td><td></td></tr>
        </table>
        <p>Sem mais para o momento,</p>
        <p>Atenciosamente,</p>
      `,
      nomeacao: `
        <p>Prezada,</p>
        <p>Na oportunidade em que a cumprimento muito cordialmente, venho por meio deste solicitar a nomeação para provimento de cargo efetivo conforme dados abaixo:</p>
        <table>
          <tr><th colspan="2">Dados necessários para a Nomeação:</th></tr>
          <tr><td>Cargo</td><td></td></tr>
          <tr><td>Quantidade</td><td></td></tr>
          <tr><td>Carga horária</td><td></td></tr>
          <tr><td>Nº Concurso</td><td></td></tr>
          <tr><td>Classificação</td><td></td></tr>
          <tr><td>Nº do Impacto</td><td></td></tr>
          <tr><td>Centro de Custo</td><td></td></tr>
          <tr><td>Local de trabalho</td><td></td></tr>
          <tr><td>Horário de trabalho</td><td></td></tr>
          <tr><td>Vacância ou Vaga criada<br><small>Obs: No caso de vacância, informar o servidor que deu origem.</small></td><td></td></tr>
        </table>
        <p>Sem mais para o momento,</p>
        <p>Atenciosamente,</p>
      `,
      impacto: `
        <p>Prezada (o),</p>
        <p>Na oportunidade em que a (o) cumprimento muito cordialmente, venho por meio deste requerer seu despacho favorável para a seguinte demanda:</p>
        <table>
          <tr><th colspan="2">Dados necessários para requerer IMPACTO ORÇAMENTÁRIO</th></tr>
          <tr><td>Cargo:</td><td></td></tr>
          <tr><td>Quantidade</td><td></td></tr>
          <tr><td>Natureza</td><td>( ) Efetivo &nbsp;&nbsp;&nbsp; ( ) Contrato</td></tr>
          <tr><td>Motivo da necessidade:</td><td></td></tr>
          <tr><td>Nome do servidor substituído (caso houver):</td><td></td></tr>
        </table>
        <p style="text-align:center">_______________________________<br>Assinatura do Secretário da pasta</p>
        <p>( ) Defiro conforme solicitado, encaminhe-se ao DRH para o devido planejamento de custos</p>
        <p>( ) Indefiro o presente pedido.</p>
        <p>Gabinete da Prefeita, ____/____/________</p>
      `,
      folha: `
        <p>Prezada (o),</p>
        <p>Na oportunidade em que a (o) cumprimento muito cordialmente, venho por meio deste requerer seu despacho favorável para a seguinte demanda:</p>
        <table>
          <tr><th colspan="2">Dados necessários para lançamento em FOLHA DE PAGAMENTO.<br>Data limite de envio: dia 15 de cada mês.</th></tr>
          <tr><td colspan="2"><small>Obs: os pedidos enviados após esta data serão lançados na competência seguinte, não havendo possibilidade de cálculo de folha complementar.</small></td></tr>
          <tr><td>Nome do servidor:</td><td></td></tr>
          <tr><td>Matrícula com o dígito:</td><td></td></tr>
          <tr><td>Cargo:</td><td></td></tr>
          <tr><td>( ) Concessão &nbsp;&nbsp;&nbsp; ( ) Cancelamento</td><td>
            ( ) Regime suplementar &nbsp; ( ) Agente de Contratação<br>
            ( ) Gratificação 20% &nbsp; ( ) Diferença de Caixa<br>
            ( ) Gratificação 40% &nbsp; ( ) Fiscalização externa<br>
            ( ) Insalubridade &nbsp; ( ) Gratificação ESF<br>
            ( ) Periculosidade &nbsp; ( ) FG 1<br>
            ( ) Verba plantão e disponibilidade &nbsp; ( ) FG 2<br>
            ( ) Adicional de sobreaviso &nbsp; ( ) FG 3<br>
            ( ) Subsídio de secretário &nbsp; ( ) FG 4<br>
            ( ) CAS &nbsp; ( ) FG 5<br>
            ( ) Sindicância &nbsp; ( ) outros __________________
          </td></tr>
          <tr><td>Dispositivo legal da verba (Nº da Lei, art., incisos, alíneas, etc.)</td><td></td></tr>
          <tr><td>Nº de horas (no caso de regime suplementar):</td><td></td></tr>
          <tr><td>Valor:</td><td></td></tr>
          <tr><td>Data de início:</td><td></td></tr>
          <tr><td>Data de término - último dia:</td><td></td></tr>
          <tr><td>Local de trabalho:</td><td></td></tr>
          <tr><td>Nome do servidor substituído (caso houver):</td><td></td></tr>
          <tr><td>Motivo da substituição (caso houver):</td><td></td></tr>
        </table>
        <p style="text-align:center">_______________________________<br>Assinatura do Secretário da pasta</p>
        <p>( ) Defiro conforme solicitado, encaminhe-se ao DRH para o devido lançamento/cancelamento em folha.</p>
        <p>( ) Indefiro o presente pedido.</p>
        <p>Gabinete da Prefeita, ____/____/________</p>
        <p style="text-align:center">_______________________________<br>Assinatura e carimbo do ordenador de despesa</p>
        <p><strong>Recebido no DRH em :</strong> ____/____/________</p>
        <p><strong>Assinatura de quem recebeu:</strong> ____________________</p>
        <p>( ) Atendido conforme solicitado</p>
        <p>( ) Indeferido</p>
        <p>Nome do servidor que atendeu o pedido:</p>
        <p>Portaria solicitada em ____/____/______</p>
      `
    };

    const memorandoSelect = document.getElementById('memorandoModelo');
    if (memorandoSelect) {
      memorandoSelect.addEventListener('change', () => {
        const key = memorandoSelect.value;
        if (!key) return;
        const editor = tinymce.get('docConteudo');
        if (!editor) return;
        const current = editor.getContent({ format: 'text' }).trim();
        if (current.length > 0) {
          const ok = window.confirm('Substituir o texto atual pelo modelo selecionado?');
          if (!ok) {
            memorandoSelect.value = '';
            return;
          }
        }
        editor.setContent(modelosMemorando[key] || '');
      });
    }

    const editModal = document.getElementById('editModal');
    if (editModal) {
      editModal.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        if (!button) return;
        const docId = button.getAttribute('data-id');
        const assunto = button.getAttribute('data-assunto') || '';
        const confidencial = button.getAttribute('data-confidencial') === '1';
        const conteudoBase64 = button.getAttribute('data-conteudo') || '';
        const destUsuarios = button.getAttribute('data-destusuarios') || '';
        const destUnidades = button.getAttribute('data-destunidades') || '';
        const destExternos = button.getAttribute('data-destexternos') || '';
        const signUsuarios = button.getAttribute('data-signusuarios') || '';
        const destUsuariosPronome = button.getAttribute('data-destusuariospronome') || '';
        const destUnidadesPronome = button.getAttribute('data-destunidadespronome') || '';

        document.getElementById('editDocumentoId').value = docId || '';
        document.getElementById('editAssunto').value = assunto;
        document.getElementById('editConfidencial').checked = confidencial;

        const editor = tinymce.get('docConteudoEdit');
        if (editor) {
          const html = conteudoBase64 ? atob(conteudoBase64) : '';
          editor.setContent(html);
        }

        const selectedUsers = destUsuarios ? JSON.parse(atob(destUsuarios)) : [];
        const selectedUnits = destUnidades ? JSON.parse(atob(destUnidades)) : [];
        const selectedSigns = signUsuarios ? JSON.parse(atob(signUsuarios)) : [];
        document.querySelectorAll('.edit-dest-user').forEach((el) => {
          el.checked = selectedUsers.includes(parseInt(el.value, 10));
        });
        document.querySelectorAll('.edit-dest-unit').forEach((el) => {
          el.checked = selectedUnits.includes(parseInt(el.value, 10));
        });

        const userPronomeMap = {};
        const unitPronomeMap = {};
        const userPronomeData = destUsuariosPronome ? JSON.parse(atob(destUsuariosPronome)) : [];
        const unitPronomeData = destUnidadesPronome ? JSON.parse(atob(destUnidadesPronome)) : [];
        userPronomeData.forEach((item) => {
          if (item && item.id) userPronomeMap[item.id] = item.pronome || '';
        });
        unitPronomeData.forEach((item) => {
          if (item && item.id) unitPronomeMap[item.id] = item.pronome || '';
        });
        document.querySelectorAll('.edit-dest-user-pronome').forEach((el) => {
          const id = parseInt(el.name.match(/\[(\d+)\]/)?.[1] || '0', 10);
          if (userPronomeMap[id] !== undefined) el.value = userPronomeMap[id];
        });
        document.querySelectorAll('.edit-dest-unit-pronome').forEach((el) => {
          const id = parseInt(el.name.match(/\[(\d+)\]/)?.[1] || '0', 10);
          if (unitPronomeMap[id] !== undefined) el.value = unitPronomeMap[id];
        });

        const externosEdit = document.getElementById('externosEdit');
        externosEdit.innerHTML = '';
        const externosData = destExternos ? JSON.parse(atob(destExternos)) : [];
        let idx = 0;
        externosData.forEach((ext) => {
          const row = document.createElement('div');
          row.className = 'row g-2 mb-2';
          row.innerHTML = `
            <div class="col-md-3">
              <input class="form-control" name="dest_externos[${idx}][nome]" placeholder="Nome" value="${(ext.nome || '').replace(/"/g, '&quot;')}">
            </div>
            <div class="col-md-3">
              <input class="form-control" name="dest_externos[${idx}][orgao]" placeholder="Órgão" value="${(ext.orgao || '').replace(/"/g, '&quot;')}">
            </div>
            <div class="col-md-3">
              <input class="form-control" name="dest_externos[${idx}][email]" placeholder="E-mail" value="${(ext.email || '').replace(/"/g, '&quot;')}">
            </div>
            <div class="col-md-3">
              <input class="form-control" name="dest_externos[${idx}][endereco]" placeholder="Endereço" value="${(ext.endereco || '').replace(/"/g, '&quot;')}">
            </div>
            <div class="col-md-4">
              <select class="form-select" name="dest_externos[${idx}][pronome]">
                <option value="">Sem tratamento</option>
                <option value="À Sra Prefeita Municipal">À Sra Prefeita Municipal</option>
                <option value="Ao Sr Prefeito Municipal">Ao Sr Prefeito Municipal</option>
                <option value="À Sra Secretária Municipal">À Sra Secretária Municipal</option>
                <option value="Ao Sr Secretário Municipal">Ao Sr Secretário Municipal</option>
                <option value="À Sra Diretora">À Sra Diretora</option>
                <option value="Ao Sr Diretor">Ao Sr Diretor</option>
                <option value="À Sra Coordenadora">À Sra Coordenadora</option>
                <option value="Ao Sr Coordenador">Ao Sr Coordenador</option>
                <option value="À Sra Presidente">À Sra Presidente</option>
                <option value="Ao Sr Presidente">Ao Sr Presidente</option>
                <option value="À Sra">À Sra</option>
                <option value="Ao Sr">Ao Sr</option>
              </select>
            </div>
          `;
          externosEdit.appendChild(row);
          if (ext.pronome) {
            const sel = row.querySelector('select');
            sel.value = ext.pronome;
          }
          idx += 1;
        });

        const editSignOrderList = document.getElementById('editSignOrderList');
        const editSignOrderInputs = document.getElementById('editSignOrderInputs');
        const editSignOrder = [];
        function renderEditOrder() {
          editSignOrderList.innerHTML = '';
          editSignOrderInputs.innerHTML = '';
          editSignOrder.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.draggable = true;
            li.dataset.value = item.value;
            li.innerHTML = `<span>${item.label}</span><span class="text-muted small">⋮⋮</span>`;
            editSignOrderList.appendChild(li);

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'sign_usuarios[]';
            input.value = item.value;
            editSignOrderInputs.appendChild(input);
          });
        }
        const signMap = new Map();
        document.querySelectorAll('.edit-sign').forEach((el) => {
          const id = parseInt(el.value, 10);
          signMap.set(id, el.getAttribute('data-label') || '');
          el.checked = selectedSigns.includes(id);
        });
        selectedSigns.forEach((id) => {
          const label = signMap.get(id) || ('Usuário #' + id);
          editSignOrder.push({ value: id, label });
        });
        renderEditOrder();

        editSignOrderList.addEventListener('dragstart', (ev) => {
          ev.dataTransfer.setData('text/plain', ev.target.dataset.value);
        });
        editSignOrderList.addEventListener('dragover', (ev) => {
          ev.preventDefault();
        });
        editSignOrderList.addEventListener('drop', (ev) => {
          ev.preventDefault();
          const fromId = parseInt(ev.dataTransfer.getData('text/plain'), 10);
          const toEl = ev.target.closest('li');
          if (!toEl) return;
          const toId = parseInt(toEl.dataset.value, 10);
          const fromIndex = editSignOrder.findIndex((i) => i.value === fromId);
          const toIndex = editSignOrder.findIndex((i) => i.value === toId);
          if (fromIndex === -1 || toIndex === -1) return;
          const [moved] = editSignOrder.splice(fromIndex, 1);
          editSignOrder.splice(toIndex, 0, moved);
          renderEditOrder();
        });

        document.querySelectorAll('.edit-sign').forEach((el) => {
          el.addEventListener('change', () => {
            const id = parseInt(el.value, 10);
            const label = el.getAttribute('data-label') || ('Usuário #' + id);
            const index = editSignOrder.findIndex((i) => i.value === id);
            if (el.checked && index === -1) {
              editSignOrder.push({ value: id, label });
            } else if (!el.checked && index !== -1) {
              editSignOrder.splice(index, 1);
            }
            renderEditOrder();
          });
        });
      });
    }

    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach((tab) => {
      tab.addEventListener('shown.bs.tab', (event) => {
        if (event.target && event.target.id === 'content-tab') {
          const editor = tinymce.get('docConteudo');
          if (editor) {
            editor.focus();
          }
        }
      });
    });

    const externosContainer = document.getElementById('externos');
    const addExternoBtn = document.getElementById('addExterno');
    let externoIndex = 0;
    function addExterno() {
      const wrapper = document.createElement('div');
      wrapper.className = 'row g-2 mb-2';
      wrapper.innerHTML = `
        <div class="col-md-3">
          <input class="form-control" name="dest_externos[${externoIndex}][nome]" placeholder="Nome">
        </div>
        <div class="col-md-3">
          <input class="form-control" name="dest_externos[${externoIndex}][orgao]" placeholder="Órgão">
        </div>
        <div class="col-md-3">
          <input class="form-control" name="dest_externos[${externoIndex}][email]" placeholder="E-mail">
        </div>
        <div class="col-md-3">
          <input class="form-control" name="dest_externos[${externoIndex}][endereco]" placeholder="Endereço">
        </div>
        <div class="col-md-4">
          <select class="form-select" name="dest_externos[${externoIndex}][pronome]">
            <option value="">Sem tratamento</option>
            <option value="À Sra Prefeita Municipal">À Sra Prefeita Municipal</option>
            <option value="Ao Sr Prefeito Municipal">Ao Sr Prefeito Municipal</option>
            <option value="À Sra Secretária Municipal">À Sra Secretária Municipal</option>
            <option value="Ao Sr Secretário Municipal">Ao Sr Secretário Municipal</option>
            <option value="À Sra Diretora">À Sra Diretora</option>
            <option value="Ao Sr Diretor">Ao Sr Diretor</option>
            <option value="À Sra Coordenadora">À Sra Coordenadora</option>
            <option value="Ao Sr Coordenador">Ao Sr Coordenador</option>
            <option value="À Sra Presidente">À Sra Presidente</option>
            <option value="Ao Sr Presidente">Ao Sr Presidente</option>
            <option value="À Sra">À Sra</option>
            <option value="Ao Sr">Ao Sr</option>
          </select>
        </div>
      `;
      externosContainer.appendChild(wrapper);
      externoIndex += 1;
    }
    addExternoBtn.addEventListener('click', addExterno);
    addExterno();

    const destSearch = document.getElementById('searchDestinatarios');
    const destItems = Array.from(document.querySelectorAll('.dest-item'));
    function normalizeText(value) {
      return (value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
    }
    if (destSearch) {
      destSearch.addEventListener('input', () => {
        const term = normalizeText(destSearch.value.trim());
        destItems.forEach((item) => {
          const label = normalizeText(item.getAttribute('data-label'));
          item.style.display = label.includes(term) ? '' : 'none';
        });
      });
    }

    const signSearch = document.getElementById('searchAssinaturas');
    const signItems = Array.from(document.querySelectorAll('.sign-item'));
    if (signSearch) {
      signSearch.addEventListener('input', () => {
        const term = normalizeText(signSearch.value.trim());
        signItems.forEach((item) => {
          const label = normalizeText(item.getAttribute('data-label'));
          item.style.display = label.includes(term) ? '' : 'none';
        });
      });
    }

    const externosEdit = document.getElementById('externosEdit');
    const addExternoEdit = document.getElementById('addExternoEdit');
    let externoEditIndex = 0;
    function addExternoRowEdit() {
      const row = document.createElement('div');
      row.className = 'row g-2 mb-2';
      row.innerHTML = `
        <div class="col-md-3">
          <input class="form-control" name="dest_externos[${externoEditIndex}][nome]" placeholder="Nome">
        </div>
        <div class="col-md-3">
          <input class="form-control" name="dest_externos[${externoEditIndex}][orgao]" placeholder="Órgão">
        </div>
        <div class="col-md-3">
          <input class="form-control" name="dest_externos[${externoEditIndex}][email]" placeholder="E-mail">
        </div>
        <div class="col-md-3">
          <input class="form-control" name="dest_externos[${externoEditIndex}][endereco]" placeholder="Endereço">
        </div>
        <div class="col-md-4">
          <select class="form-select" name="dest_externos[${externoEditIndex}][pronome]">
            <option value="">Sem tratamento</option>
            <option value="À Sra Prefeita Municipal">À Sra Prefeita Municipal</option>
            <option value="Ao Sr Prefeito Municipal">Ao Sr Prefeito Municipal</option>
            <option value="À Sra Secretária Municipal">À Sra Secretária Municipal</option>
            <option value="Ao Sr Secretário Municipal">Ao Sr Secretário Municipal</option>
            <option value="À Sra Diretora">À Sra Diretora</option>
            <option value="Ao Sr Diretor">Ao Sr Diretor</option>
            <option value="À Sra Coordenadora">À Sra Coordenadora</option>
            <option value="Ao Sr Coordenador">Ao Sr Coordenador</option>
            <option value="À Sra Presidente">À Sra Presidente</option>
            <option value="Ao Sr Presidente">Ao Sr Presidente</option>
            <option value="À Sra">À Sra</option>
            <option value="Ao Sr">Ao Sr</option>
          </select>
        </div>
      `;
      externosEdit.appendChild(row);
      externoEditIndex += 1;
    }
    if (addExternoEdit) {
      addExternoEdit.addEventListener('click', addExternoRowEdit);
    }

    const signOrder = [];
    const signOrderList = document.getElementById('signOrderList');
    const signOrderInputs = document.getElementById('signOrderInputs');
    function renderSignOrder() {
      signOrderList.innerHTML = '';
      signOrderInputs.innerHTML = '';
      signOrder.forEach((item) => {
        const li = document.createElement('li');
        li.className = 'list-group-item';
        li.textContent = item.label;
        signOrderList.appendChild(li);

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'sign_usuarios[]';
        input.value = item.value;
        signOrderInputs.appendChild(input);
      });
    }
    document.querySelectorAll('.sign-checkbox').forEach((checkbox) => {
      checkbox.addEventListener('change', () => {
        const value = checkbox.value;
        const label = checkbox.getAttribute('data-label') || '';
        const index = signOrder.findIndex((item) => item.value === value);
        if (checkbox.checked && index === -1) {
          signOrder.push({ value, label });
        } else if (!checkbox.checked && index !== -1) {
          signOrder.splice(index, 1);
        }
        renderSignOrder();
      });
    });
  </script>
</body>
</html>
