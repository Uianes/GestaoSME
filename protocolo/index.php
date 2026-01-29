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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
  <style>
    body { background: #f6f8fb; }
    .card { border-radius: 14px; }
    .quill-editor { min-height: 240px; }
    .badge-status { text-transform: capitalize; }
  </style>
</head>
<body>
  <div class="container-fluid py-4 px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
      <div>
        <h2 class="mb-1">Protocolo Eletrônico</h2>
        <div class="text-muted">Crie, registre e encaminhe documentos oficiais.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#docModal">
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
                <?php if ($numeracao): ?>
                  <span class="badge text-bg-primary"><?= h($numeracao['codigo_formatado']) ?></span>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <div class="text-muted small">Conteúdo</div>
                <div class="border rounded p-3 bg-white">
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
                        <?php if ($dest['tipo_destino'] === 'interno'): ?>
                          <?= $dest['usuario_destino'] ? 'Usuário #' . h($dest['usuario_destino']) : 'Unidade #' . h($dest['id_unidade_destino']) ?>
                        <?php else: ?>
                          <?= h($dest['nome_externo']) ?> (<?= h($dest['email_externo']) ?>)
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
              <div class="col-md-8">
                <label class="form-label">Nível de sigilo</label>
                <input type="text" class="form-control" name="nivel_sigilo" placeholder="Opcional">
              </div>
              <div class="col-12">
                <label class="form-label">Conteúdo</label>
                <div id="editor" class="quill-editor bg-white border rounded"></div>
                <input type="hidden" name="conteudo" id="conteudo">
              </div>
              <div class="col-md-6">
                <label class="form-label">Destinatários (usuários)</label>
                <div class="border rounded p-2" style="max-height: 240px; overflow:auto;">
                  <?php foreach ($usuarios as $usuario): ?>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="dest_usuarios[]" value="<?= (int)$usuario['matricula'] ?>" id="user-<?= (int)$usuario['matricula'] ?>">
                      <label class="form-check-label" for="user-<?= (int)$usuario['matricula'] ?>">
                        <?= h($usuario['nome']) ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Destinatários (unidades)</label>
                <div class="border rounded p-2" style="max-height: 240px; overflow:auto;">
                  <?php foreach ($unidades as $unidade): ?>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="dest_unidades[]" value="<?= (int)$unidade['id_unidade'] ?>" id="unit-<?= (int)$unidade['id_unidade'] ?>">
                      <label class="form-check-label" for="unit-<?= (int)$unidade['id_unidade'] ?>">
                        <?= h($unidade['nome']) ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Assinaturas (ordem)</label>
                <div class="border rounded p-2" style="max-height: 200px; overflow:auto;">
                  <?php foreach ($usuarios as $usuario): ?>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="sign_usuarios[]" value="<?= (int)$usuario['matricula'] ?>" id="sign-<?= (int)$usuario['matricula'] ?>">
                      <label class="form-check-label" for="sign-<?= (int)$usuario['matricula'] ?>">
                        <?= h($usuario['nome']) ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="form-text">A ordem segue a lista acima.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Destinatários externos</label>
                <div id="externos"></div>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="addExterno">Adicionar destinatário externo</button>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
  <script>
    const quill = new Quill('#editor', { theme: 'snow' });
    document.getElementById('docForm').addEventListener('submit', () => {
      document.getElementById('conteudo').value = quill.root.innerHTML;
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
      `;
      externosContainer.appendChild(wrapper);
      externoIndex += 1;
    }
    addExternoBtn.addEventListener('click', addExterno);
    addExterno();
  </script>
</body>
</html>
