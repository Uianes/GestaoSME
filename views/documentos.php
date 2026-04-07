<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../config/db.php';

require_login();

if (!user_can_access_system('documentos') && !user_can_access_system('protocolo')) {
    http_response_code(403);
    ?>
    <div class="alert alert-danger mb-0">Você não tem permissão para acessar o repositório de documentos.</div>
    <?php
    return;
}

$conn = db();
$matricula = (int)($_SESSION['user']['matricula'] ?? 0);

function h_doc_repo($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function doc_repo_run_query(mysqli $conn, string $sql, array $params = []): mysqli_result|bool
{
    try {
        if ($params === []) {
            return $conn->query($sql);
        }
        return mysqli_execute_query($conn, $sql, $params);
    } catch (Throwable $e) {
        return false;
    }
}

function doc_repo_table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function doc_repo_column_exists(mysqli $conn, string $table, string $column): bool
{
    $tableSafe = $conn->real_escape_string($table);
    $columnSafe = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

$schemaOk = doc_repo_table_exists($conn, 'doc_documentos')
    && doc_repo_table_exists($conn, 'doc_status')
    && doc_repo_table_exists($conn, 'doc_tipos')
    && doc_repo_table_exists($conn, 'doc_versoes')
    && doc_repo_table_exists($conn, 'usuarios')
    && doc_repo_table_exists($conn, 'unidade')
    && doc_repo_table_exists($conn, 'vinculo')
    && doc_repo_table_exists($conn, 'doc_destinatarios')
    && doc_repo_table_exists($conn, 'doc_permissoes')
    && doc_repo_table_exists($conn, 'doc_numeracao')
    && doc_repo_column_exists($conn, 'doc_documentos', 'id')
    && doc_repo_column_exists($conn, 'doc_documentos', 'tipo_id')
    && doc_repo_column_exists($conn, 'doc_documentos', 'id_unidade_origem')
    && doc_repo_column_exists($conn, 'doc_documentos', 'criado_por')
    && doc_repo_column_exists($conn, 'doc_documentos', 'status_id')
    && doc_repo_column_exists($conn, 'doc_documentos', 'assunto')
    && doc_repo_column_exists($conn, 'doc_documentos', 'confidencial')
    && doc_repo_column_exists($conn, 'doc_documentos', 'criado_em')
    && doc_repo_column_exists($conn, 'doc_documentos', 'atualizado_em');

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'autor' => (int)($_GET['autor'] ?? 0),
    'tipo' => (int)($_GET['tipo'] ?? 0),
    'status' => (int)($_GET['status'] ?? 0),
    'unidade' => (int)($_GET['unidade'] ?? 0),
    'confidencial' => trim((string)($_GET['confidencial'] ?? '')),
    'criado_de' => trim((string)($_GET['criado_de'] ?? '')),
    'criado_ate' => trim((string)($_GET['criado_ate'] ?? '')),
    'editado_de' => trim((string)($_GET['editado_de'] ?? '')),
    'editado_ate' => trim((string)($_GET['editado_ate'] ?? '')),
];
$selectedDocId = (int)($_GET['doc'] ?? 0);
$pageNum = max(1, (int)($_GET['pagina'] ?? 1));
$perPage = 20;
$offset = ($pageNum - 1) * $perPage;

$authors = [];
$types = [];
$statuses = [];
$units = [];
$docs = [];
$docTotal = 0;
$totalPages = 1;
$selectedDoc = null;
$selectedVersion = null;
$selectedAttachments = [];
$selectedRecipients = [];
$selectedAudit = [];
$selectedNumber = null;
$errorMessage = null;

if ($schemaOk) {
    $resAuthors = doc_repo_run_query($conn, 'SELECT matricula, nome FROM usuarios WHERE ativo = 1 ORDER BY nome');
    if ($resAuthors instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($resAuthors)) {
            $authors[] = $row;
        }
    }

    $resTypes = doc_repo_run_query($conn, 'SELECT id, nome FROM doc_tipos WHERE ativo = 1 ORDER BY nome');
    if ($resTypes instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($resTypes)) {
            $types[] = $row;
        }
    }

    $resStatuses = doc_repo_run_query($conn, 'SELECT id, nome FROM doc_status WHERE ativo = 1 ORDER BY ordem, nome');
    if ($resStatuses instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($resStatuses)) {
            $statuses[] = $row;
        }
    }

    $resUnits = doc_repo_run_query($conn, 'SELECT id_unidade, nome FROM unidade ORDER BY nome');
    if ($resUnits instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($resUnits)) {
            $units[] = $row;
        }
    }

    $accessClause = '
        (
            d.criado_por = ?
            OR EXISTS (
                SELECT 1
                FROM doc_destinatarios dd1
                WHERE dd1.documento_id = d.id
                  AND dd1.usuario_destino = ?
            )
            OR EXISTS (
                SELECT 1
                FROM doc_permissoes dp1
                WHERE dp1.documento_id = d.id
                  AND dp1.usuario = ?
            )
            OR EXISTS (
                SELECT 1
                FROM doc_destinatarios dd2
                INNER JOIN vinculo v2 ON v2.id_unidade = dd2.id_unidade_destino
                WHERE dd2.documento_id = d.id
                  AND dd2.id_unidade_destino IS NOT NULL
                  AND v2.matricula = ?
            )
        )
    ';

    $where = ["{$accessClause}"];
    $params = [$matricula, $matricula, $matricula, $matricula];

    if ($filters['q'] !== '') {
        $like = '%' . $filters['q'] . '%';
        $where[] = '(
            d.assunto LIKE ?
            OR COALESCE(n.codigo_formatado, "") LIKE ?
            OR COALESCE(u.nome, "") LIKE ?
            OR COALESCE(au.nome, "") LIKE ?
            OR COALESCE(t.nome, "") LIKE ?
            OR EXISTS (
                SELECT 1
                FROM doc_versoes dvq
                WHERE dvq.documento_id = d.id
                  AND dvq.conteudo LIKE ?
            )
            OR EXISTS (
                SELECT 1
                FROM doc_anexos daq
                WHERE daq.documento_id = d.id
                  AND daq.nome_arquivo LIKE ?
            )
        )';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }
    if ($filters['autor'] > 0) {
        $where[] = 'd.criado_por = ?';
        $params[] = $filters['autor'];
    }
    if ($filters['tipo'] > 0) {
        $where[] = 'd.tipo_id = ?';
        $params[] = $filters['tipo'];
    }
    if ($filters['status'] > 0) {
        $where[] = 'd.status_id = ?';
        $params[] = $filters['status'];
    }
    if ($filters['unidade'] > 0) {
        $where[] = 'd.id_unidade_origem = ?';
        $params[] = $filters['unidade'];
    }
    if ($filters['confidencial'] === '1' || $filters['confidencial'] === '0') {
        $where[] = 'd.confidencial = ?';
        $params[] = (int)$filters['confidencial'];
    }
    if ($filters['criado_de'] !== '') {
        $where[] = 'DATE(d.criado_em) >= ?';
        $params[] = $filters['criado_de'];
    }
    if ($filters['criado_ate'] !== '') {
        $where[] = 'DATE(d.criado_em) <= ?';
        $params[] = $filters['criado_ate'];
    }
    if ($filters['editado_de'] !== '') {
        $where[] = 'DATE(d.atualizado_em) >= ?';
        $params[] = $filters['editado_de'];
    }
    if ($filters['editado_ate'] !== '') {
        $where[] = 'DATE(d.atualizado_em) <= ?';
        $params[] = $filters['editado_ate'];
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $countSql = "
        SELECT COUNT(DISTINCT d.id) AS total
        FROM doc_documentos d
        INNER JOIN doc_status s ON s.id = d.status_id
        INNER JOIN doc_tipos t ON t.id = d.tipo_id
        LEFT JOIN usuarios au ON au.matricula = d.criado_por
        LEFT JOIN unidade u ON u.id_unidade = d.id_unidade_origem
        LEFT JOIN doc_numeracao n ON n.documento_id = d.id
        {$whereSql}
    ";
    $resCount = doc_repo_run_query($conn, $countSql, $params);
    if ($resCount instanceof mysqli_result) {
        $docTotal = (int)(mysqli_fetch_assoc($resCount)['total'] ?? 0);
        $totalPages = max(1, (int)ceil($docTotal / $perPage));
        if ($pageNum > $totalPages) {
            $pageNum = $totalPages;
            $offset = ($pageNum - 1) * $perPage;
        }
    } else {
        $errorMessage = 'Não foi possível consultar a quantidade de documentos.';
    }

    if ($errorMessage === null) {
        $listSql = "
            SELECT DISTINCT
                d.id,
                d.assunto,
                d.confidencial,
                d.criado_em,
                d.atualizado_em,
                s.nome AS status_nome,
                t.nome AS tipo_nome,
                au.nome AS autor_nome,
                u.nome AS unidade_nome,
                n.codigo_formatado,
                (
                    SELECT MAX(dvx.criado_em)
                    FROM doc_versoes dvx
                    WHERE dvx.documento_id = d.id
                ) AS ultima_versao_em
            FROM doc_documentos d
            INNER JOIN doc_status s ON s.id = d.status_id
            INNER JOIN doc_tipos t ON t.id = d.tipo_id
            LEFT JOIN usuarios au ON au.matricula = d.criado_por
            LEFT JOIN unidade u ON u.id_unidade = d.id_unidade_origem
            LEFT JOIN doc_numeracao n ON n.documento_id = d.id
            {$whereSql}
            ORDER BY d.atualizado_em DESC, d.criado_em DESC, d.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $resDocs = doc_repo_run_query($conn, $listSql, $params);
        if ($resDocs instanceof mysqli_result) {
            while ($row = mysqli_fetch_assoc($resDocs)) {
                $docs[] = $row;
            }
        } else {
            $errorMessage = 'Não foi possível carregar a listagem de documentos.';
        }
    }

    if ($selectedDocId > 0) {
        $detailSql = "
            SELECT
                d.*,
                s.nome AS status_nome,
                t.nome AS tipo_nome,
                au.nome AS autor_nome,
                u.nome AS unidade_nome,
                n.codigo_formatado
            FROM doc_documentos d
            INNER JOIN doc_status s ON s.id = d.status_id
            INNER JOIN doc_tipos t ON t.id = d.tipo_id
            LEFT JOIN usuarios au ON au.matricula = d.criado_por
            LEFT JOIN unidade u ON u.id_unidade = d.id_unidade_origem
            LEFT JOIN doc_numeracao n ON n.documento_id = d.id
            WHERE d.id = ?
              AND {$accessClause}
            LIMIT 1
        ";
        $detailParams = [$selectedDocId, $matricula, $matricula, $matricula, $matricula];
        $resDetail = doc_repo_run_query($conn, $detailSql, $detailParams);
        if ($resDetail instanceof mysqli_result) {
            $selectedDoc = mysqli_fetch_assoc($resDetail) ?: null;
        }

        if ($selectedDoc) {
            $resVersion = doc_repo_run_query(
                $conn,
                'SELECT * FROM doc_versoes WHERE documento_id = ? ORDER BY numero_versao DESC, criado_em DESC LIMIT 1',
                [$selectedDocId]
            );
            if ($resVersion instanceof mysqli_result) {
                $selectedVersion = mysqli_fetch_assoc($resVersion) ?: null;
            }

            $resAttachments = doc_repo_run_query(
                $conn,
                'SELECT * FROM doc_anexos WHERE documento_id = ? ORDER BY enviado_em DESC',
                [$selectedDocId]
            );
            if ($resAttachments instanceof mysqli_result) {
                while ($row = mysqli_fetch_assoc($resAttachments)) {
                    $selectedAttachments[] = $row;
                }
            }

            $resRecipients = doc_repo_run_query(
                $conn,
                'SELECT * FROM doc_destinatarios WHERE documento_id = ? ORDER BY ordem',
                [$selectedDocId]
            );
            if ($resRecipients instanceof mysqli_result) {
                while ($row = mysqli_fetch_assoc($resRecipients)) {
                    $selectedRecipients[] = $row;
                }
            }

            $resAudit = doc_repo_run_query(
                $conn,
                'SELECT a.*, u.nome AS usuario_nome
                 FROM doc_auditoria a
                 LEFT JOIN usuarios u ON u.matricula = a.usuario
                 WHERE a.entidade = "documento" AND a.entidade_id = ?
                 ORDER BY a.criado_em DESC
                 LIMIT 30',
                [$selectedDocId]
            );
            if ($resAudit instanceof mysqli_result) {
                while ($row = mysqli_fetch_assoc($resAudit)) {
                    $selectedAudit[] = $row;
                }
            }

            $selectedNumber = !empty($selectedDoc['codigo_formatado']) ? $selectedDoc['codigo_formatado'] : null;
        }
    }
} else {
    $errorMessage = 'O schema do módulo de documentos não está completo neste banco.';
}

$statusClassMap = [
    'rascunho' => 'secondary',
    'revisao' => 'info',
    'assinatura' => 'warning',
    'assinado' => 'primary',
    'enviado' => 'success',
    'arquivado' => 'dark',
    'cancelado' => 'danger',
];

$queryBase = $_GET;
unset($queryBase['doc'], $queryBase['pagina']);
?>

<style>
    .doc-repo-result-item .min-w-0 {
        min-width: 0;
        flex: 1 1 auto;
    }

    .doc-repo-result-item .fw-semibold,
    .doc-repo-result-item .small {
        overflow-wrap: anywhere;
        word-break: break-word;
        white-space: normal;
    }

    .doc-repo-result-item .badge {
        max-width: 100%;
        white-space: normal;
    }
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="mb-1 text-body-emphasis">Repositório de Documentos</h3>
        <div class="text-muted">Consulta e leitura de documentos cadastrados no protocolo.</div>
    </div>
    <a class="btn btn-outline-primary" href="<?= h_doc_repo(url('app.php?page=protocolo')) ?>">
        Abrir Protocolo
    </a>
</div>

<?php if ($errorMessage !== null): ?>
    <div class="alert alert-warning"><?= h_doc_repo($errorMessage) ?></div>
<?php endif; ?>

<form method="get" action="app.php" class="card shadow-sm mb-4">
    <div class="card-body">
        <input type="hidden" name="page" value="documentos">
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="doc-q">Buscar</label>
                <input
                    type="text"
                    class="form-control"
                    id="doc-q"
                    name="q"
                    value="<?= h_doc_repo($filters['q']) ?>"
                    placeholder="Assunto, número, autor, conteúdo, anexo..."
                >
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label" for="doc-autor">Autor</label>
                <select class="form-select" id="doc-autor" name="autor">
                    <option value="0">Todos</option>
                    <?php foreach ($authors as $author): ?>
                        <option value="<?= (int)$author['matricula'] ?>" <?= $filters['autor'] === (int)$author['matricula'] ? 'selected' : '' ?>>
                            <?= h_doc_repo($author['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label" for="doc-tipo">Tipo</label>
                <select class="form-select" id="doc-tipo" name="tipo">
                    <option value="0">Todos</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= (int)$type['id'] ?>" <?= $filters['tipo'] === (int)$type['id'] ? 'selected' : '' ?>>
                            <?= h_doc_repo($type['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label" for="doc-status">Status</label>
                <select class="form-select" id="doc-status" name="status">
                    <option value="0">Todos</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= (int)$status['id'] ?>" <?= $filters['status'] === (int)$status['id'] ? 'selected' : '' ?>>
                            <?= h_doc_repo($status['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label" for="doc-unidade">Unidade</label>
                <select class="form-select" id="doc-unidade" name="unidade">
                    <option value="0">Todas</option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int)$unit['id_unidade'] ?>" <?= $filters['unidade'] === (int)$unit['id_unidade'] ? 'selected' : '' ?>>
                            <?= h_doc_repo($unit['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label" for="doc-confidencial">Sigilo</label>
                <select class="form-select" id="doc-confidencial" name="confidencial">
                    <option value="">Todos</option>
                    <option value="1" <?= $filters['confidencial'] === '1' ? 'selected' : '' ?>>Confidencial</option>
                    <option value="0" <?= $filters['confidencial'] === '0' ? 'selected' : '' ?>>Não confidencial</option>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label" for="doc-criado-de">Criado de</label>
                <input type="date" class="form-control" id="doc-criado-de" name="criado_de" value="<?= h_doc_repo($filters['criado_de']) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label" for="doc-criado-ate">Criado até</label>
                <input type="date" class="form-control" id="doc-criado-ate" name="criado_ate" value="<?= h_doc_repo($filters['criado_ate']) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label" for="doc-editado-de">Editado de</label>
                <input type="date" class="form-control" id="doc-editado-de" name="editado_de" value="<?= h_doc_repo($filters['editado_de']) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label" for="doc-editado-ate">Editado até</label>
                <input type="date" class="form-control" id="doc-editado-ate" name="editado_ate" value="<?= h_doc_repo($filters['editado_ate']) ?>">
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a class="btn btn-outline-secondary" href="app.php?page=documentos">Limpar</a>
            </div>
        </div>
    </div>
</form>

<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Resultados</h5>
                    <span class="text-muted small"><?= number_format($docTotal, 0, ',', '.') ?> documento(s)</span>
                </div>

                <?php if (empty($docs)): ?>
                    <p class="text-muted mb-0">Nenhum documento encontrado com os filtros informados.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($docs as $doc): ?>
                            <?php
                            $itemQuery = $queryBase;
                            $itemQuery['page'] = 'documentos';
                            $itemQuery['doc'] = (int)$doc['id'];
                            $itemQuery['pagina'] = $pageNum;
                            $statusKey = strtolower((string)($doc['status_nome'] ?? ''));
                            ?>
                            <a class="list-group-item list-group-item-action doc-repo-result-item <?= $selectedDocId === (int)$doc['id'] ? 'active' : '' ?>" href="app.php?<?= h_doc_repo(http_build_query($itemQuery)) ?>">
                                <div class="d-flex justify-content-between gap-3">
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate"><?= h_doc_repo($doc['assunto']) ?></div>
                                        <div class="small <?= $selectedDocId === (int)$doc['id'] ? 'text-white-50' : 'text-muted' ?>">
                                            <?= h_doc_repo($doc['tipo_nome']) ?>
                                            <?php if (!empty($doc['codigo_formatado'])): ?>
                                                • <?= h_doc_repo($doc['codigo_formatado']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small <?= $selectedDocId === (int)$doc['id'] ? 'text-white-50' : 'text-muted' ?>">
                                            <?= h_doc_repo($doc['autor_nome'] ?? 'Autor não identificado') ?>
                                            • <?= h_doc_repo($doc['unidade_nome'] ?? 'Sem unidade') ?>
                                        </div>
                                        <div class="small <?= $selectedDocId === (int)$doc['id'] ? 'text-white-50' : 'text-muted' ?>">
                                            Criado em <?= date('d/m/Y H:i', strtotime($doc['criado_em'])) ?>
                                            • Editado em <?= date('d/m/Y H:i', strtotime($doc['atualizado_em'])) ?>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column align-items-end gap-2">
                                        <span class="badge text-bg-<?= $statusClassMap[$statusKey] ?? 'light' ?>">
                                            <?= h_doc_repo($doc['status_nome']) ?>
                                        </span>
                                        <?php if ((int)$doc['confidencial'] === 1): ?>
                                            <span class="badge text-bg-danger">Confidencial</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-transparent">
                    <nav aria-label="Paginação dos documentos">
                        <ul class="pagination pagination-sm mb-0 flex-wrap">
                            <?php
                            $prevQuery = $queryBase;
                            $prevQuery['page'] = 'documentos';
                            $prevQuery['pagina'] = max(1, $pageNum - 1);
                            if ($selectedDocId > 0) {
                                $prevQuery['doc'] = $selectedDocId;
                            }
                            $nextQuery = $queryBase;
                            $nextQuery['page'] = 'documentos';
                            $nextQuery['pagina'] = min($totalPages, $pageNum + 1);
                            if ($selectedDocId > 0) {
                                $nextQuery['doc'] = $selectedDocId;
                            }
                            ?>
                            <li class="page-item <?= $pageNum <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="app.php?<?= h_doc_repo(http_build_query($prevQuery)) ?>">Anterior</a>
                            </li>
                            <li class="page-item disabled">
                                <span class="page-link">Página <?= $pageNum ?> de <?= $totalPages ?></span>
                            </li>
                            <li class="page-item <?= $pageNum >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="app.php?<?= h_doc_repo(http_build_query($nextQuery)) ?>">Próxima</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <?php if (!$selectedDoc): ?>
                    <div class="text-muted">Selecione um documento na lista para ler o conteúdo.</div>
                <?php else: ?>
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                        <div>
                            <h4 class="mb-1"><?= h_doc_repo($selectedDoc['assunto']) ?></h4>
                            <div class="text-muted small">
                                <?= h_doc_repo($selectedDoc['tipo_nome']) ?>
                                <?php if ($selectedNumber): ?>
                                    • <?= h_doc_repo($selectedNumber) ?>
                                <?php endif; ?>
                                • <?= h_doc_repo($selectedDoc['status_nome']) ?>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ((int)$selectedDoc['confidencial'] === 1): ?>
                                <span class="badge text-bg-danger">Confidencial</span>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= h_doc_repo(url('protocolo/index.php?doc=' . (int)$selectedDoc['id'])) ?>" target="_blank">Abrir no protocolo</a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= h_doc_repo(url('protocolo/print.php?doc=' . (int)$selectedDoc['id'])) ?>" target="_blank">Visualizar impressão</a>
                            <a class="btn btn-sm btn-primary" href="<?= h_doc_repo(url('protocolo/pdf.php?doc=' . (int)$selectedDoc['id'])) ?>" target="_blank">Baixar PDF</a>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <div class="small text-muted">Autor</div>
                            <div><?= h_doc_repo($selectedDoc['autor_nome'] ?? 'Autor não identificado') ?></div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="small text-muted">Unidade de origem</div>
                            <div><?= h_doc_repo($selectedDoc['unidade_nome'] ?? 'Sem unidade') ?></div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="small text-muted">Criado em</div>
                            <div><?= date('d/m/Y H:i', strtotime($selectedDoc['criado_em'])) ?></div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="small text-muted">Última edição</div>
                            <div><?= date('d/m/Y H:i', strtotime($selectedDoc['atualizado_em'])) ?></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-3">Conteúdo</h5>
                        <div class="border rounded bg-body-tertiary p-3 overflow-auto">
                            <?php if ($selectedVersion): ?>
                                <?= $selectedVersion['conteudo'] ?>
                            <?php else: ?>
                                <span class="text-muted">Sem versão registrada para este documento.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-3">Destinatários</h5>
                        <?php if (empty($selectedRecipients)): ?>
                            <div class="text-muted">Nenhum destinatário registrado.</div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush border rounded">
                                <?php foreach ($selectedRecipients as $recipient): ?>
                                    <li class="list-group-item">
                                        <?php if ($recipient['tipo_destino'] === 'interno'): ?>
                                            <?= $recipient['usuario_destino'] ? 'Usuário #' . h_doc_repo($recipient['usuario_destino']) : 'Unidade #' . h_doc_repo($recipient['id_unidade_destino']) ?>
                                        <?php else: ?>
                                            <?= h_doc_repo($recipient['nome_externo'] ?: 'Destinatário externo') ?>
                                            <?php if (!empty($recipient['orgao_externo'])): ?>
                                                • <?= h_doc_repo($recipient['orgao_externo']) ?>
                                            <?php endif; ?>
                                            <?php if (!empty($recipient['email_externo'])): ?>
                                                • <?= h_doc_repo($recipient['email_externo']) ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-3">Anexos</h5>
                        <?php if (empty($selectedAttachments)): ?>
                            <div class="text-muted">Nenhum anexo vinculado.</div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush border rounded">
                                <?php foreach ($selectedAttachments as $attachment): ?>
                                    <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div>
                                            <div class="fw-semibold"><?= h_doc_repo($attachment['nome_arquivo']) ?></div>
                                            <div class="small text-muted">
                                                <?= h_doc_repo($attachment['mime_type']) ?>
                                                • <?= number_format((int)$attachment['tamanho_bytes'] / 1024, 1, ',', '.') ?> KB
                                            </div>
                                        </div>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= h_doc_repo(url($attachment['caminho_storage'])) ?>" target="_blank">Abrir anexo</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h5 class="mb-3">Histórico</h5>
                        <?php if (empty($selectedAudit)): ?>
                            <div class="text-muted">Nenhum evento de auditoria encontrado.</div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush border rounded">
                                <?php foreach ($selectedAudit as $audit): ?>
                                    <li class="list-group-item">
                                        <div class="fw-semibold"><?= h_doc_repo($audit['evento']) ?></div>
                                        <div class="small text-muted">
                                            <?= h_doc_repo($audit['usuario_nome'] ?? 'Sistema') ?>
                                            • <?= date('d/m/Y H:i', strtotime($audit['criado_em'])) ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
