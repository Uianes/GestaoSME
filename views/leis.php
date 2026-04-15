<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../config/db.php';

require_login();

if (!user_can_access_system('leis')) {
    http_response_code(403);
    ?>
    <div class="alert alert-danger mb-0">Você não tem permissão para acessar o repositório de leis.</div>
    <?php
    return;
}

$conn = db();
$userMatricula = (int)($_SESSION['user']['matricula'] ?? 0);
$canManage = user_can_access_system('leis');

function h_lei($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function lei_repo_run_query(mysqli $conn, string $sql, array $params = []): mysqli_result|bool
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

function lei_repo_table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function lei_repo_column_exists(mysqli $conn, string $table, string $column): bool
{
    $tableSafe = $conn->real_escape_string($table);
    $columnSafe = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function lei_repo_slug(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim((string)$value, '-');
}

function lei_repo_allowed_uploads(): array
{
    return [
        'pdf' => ['application/pdf'],
    ];
}

function lei_repo_fetch_arquivo_atual(mysqli $conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $result = lei_repo_run_query(
        $conn,
        'SELECT arquivo_caminho, arquivo_nome_original, arquivo_mime FROM leis_repositorio WHERE id = ? LIMIT 1',
        [$id]
    );
    if (!$result instanceof mysqli_result) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    return is_array($row) ? $row : null;
}

function lei_repo_remove_file(?string $relativePath): void
{
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') {
        return;
    }
    $fullPath = realpath(__DIR__ . '/../' . $relativePath);
    $baseDir = realpath(__DIR__ . '/../uploads/leis');
    if ($fullPath === false || $baseDir === false) {
        return;
    }
    if (strncmp($fullPath, $baseDir, strlen($baseDir)) !== 0) {
        return;
    }
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function lei_repo_store_file(int $registroId, array $file): array
{
    if ($registroId <= 0) {
        throw new RuntimeException('Registro inválido para upload do PDF.');
    }
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return [];
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload do PDF.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 20 * 1024 * 1024) {
        throw new RuntimeException('O PDF deve ter no máximo 20 MB.');
    }

    $originalName = basename((string)($file['name'] ?? ''));
    if ($originalName === '') {
        throw new RuntimeException('Nome do arquivo inválido.');
    }

    $allowed = lei_repo_allowed_uploads();
    $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '' || !isset($allowed[$extension])) {
        throw new RuntimeException('Formato inválido. Envie um PDF.');
    }

    $detectedMime = mime_content_type((string)($file['tmp_name'] ?? '')) ?: ((string)($file['type'] ?? 'application/octet-stream'));
    if (!in_array($detectedMime, $allowed[$extension], true)) {
        throw new RuntimeException('Tipo de arquivo inválido.');
    }

    $folder = __DIR__ . '/../uploads/leis/' . $registroId;
    if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
        throw new RuntimeException('Não foi possível criar a pasta do arquivo.');
    }

    $filename = uniqid('lei_', true) . '.' . $extension;
    $target = $folder . '/' . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException('Não foi possível salvar o arquivo.');
    }

    return [
        'arquivo_caminho' => 'uploads/leis/' . $registroId . '/' . $filename,
        'arquivo_nome_original' => $originalName,
        'arquivo_mime' => $detectedMime,
    ];
}

function lei_repo_normalize_tags(string $raw): array
{
    $parts = preg_split('/[,;\n]+/', $raw) ?: [];
    $tags = [];
    foreach ($parts as $part) {
        $name = trim($part);
        if ($name === '') {
            continue;
        }
        $tags[mb_strtolower($name, 'UTF-8')] = $name;
    }
    return array_values($tags);
}

function lei_repo_sync_tags(mysqli $conn, int $leiId, array $tagNames, int $userMatricula): void
{
    lei_repo_run_query($conn, 'DELETE FROM leis_repositorio_tags WHERE lei_id = ?', [$leiId]);
    foreach ($tagNames as $tagName) {
        $slug = lei_repo_slug($tagName);
        if ($slug === '') {
            continue;
        }
        $existing = lei_repo_run_query($conn, 'SELECT id FROM leis_tags WHERE slug = ? LIMIT 1', [$slug]);
        $tagId = 0;
        if ($existing instanceof mysqli_result) {
            $tagId = (int)(mysqli_fetch_assoc($existing)['id'] ?? 0);
        }
        if ($tagId <= 0) {
            $insertTag = lei_repo_run_query(
                $conn,
                'INSERT INTO leis_tags (nome, slug, criado_por) VALUES (?, ?, ?)',
                [$tagName, $slug, $userMatricula > 0 ? $userMatricula : null]
            );
            if ($insertTag === false) {
                throw new RuntimeException('Falha ao criar a tag "' . $tagName . '".');
            }
            $tagId = (int)$conn->insert_id;
        }
        $linked = lei_repo_run_query(
            $conn,
            'INSERT INTO leis_repositorio_tags (lei_id, tag_id) VALUES (?, ?)',
            [$leiId, $tagId]
        );
        if ($linked === false) {
            throw new RuntimeException('Falha ao vincular a tag "' . $tagName . '".');
        }
    }
}

$schemaOk = lei_repo_table_exists($conn, 'leis_repositorio')
    && lei_repo_table_exists($conn, 'leis_tags')
    && lei_repo_table_exists($conn, 'leis_repositorio_tags')
    && lei_repo_column_exists($conn, 'leis_repositorio', 'id')
    && lei_repo_column_exists($conn, 'leis_repositorio', 'titulo')
    && lei_repo_column_exists($conn, 'leis_repositorio', 'descricao')
    && lei_repo_column_exists($conn, 'leis_repositorio', 'tipo_fonte')
    && lei_repo_column_exists($conn, 'leis_repositorio', 'link_url')
    && lei_repo_column_exists($conn, 'leis_repositorio', 'arquivo_caminho')
    && lei_repo_column_exists($conn, 'leis_repositorio', 'arquivo_nome_original')
    && lei_repo_column_exists($conn, 'leis_repositorio', 'arquivo_mime')
    && lei_repo_column_exists($conn, 'leis_tags', 'id')
    && lei_repo_column_exists($conn, 'leis_tags', 'nome')
    && lei_repo_column_exists($conn, 'leis_tags', 'slug')
    && lei_repo_column_exists($conn, 'leis_repositorio_tags', 'lei_id')
    && lei_repo_column_exists($conn, 'leis_repositorio_tags', 'tag_id');

$errors = [];
$notice = null;
$selectedLeiId = (int)($_GET['lei'] ?? 0);
$searchTerm = trim((string)($_GET['q'] ?? ''));
$tagTerm = trim((string)($_GET['tag'] ?? ''));
$modalOpen = false;
$modalMode = 'create';
$formData = [];
$selectedLei = null;
$allTags = [];
$leis = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schemaOk) {
    if (!$canManage) {
        $errors[] = 'Você não tem permissão para alterar dados.';
    } else {
        $action = (string)($_POST['action'] ?? 'save');
        $id = (int)($_POST['id'] ?? 0);
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $tipoFonte = trim((string)($_POST['tipo_fonte'] ?? 'link_externo'));
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        $removeArquivo = isset($_POST['remove_arquivo']) ? 1 : 0;
        $tagsTexto = trim((string)($_POST['tags_texto'] ?? ''));
        $modalOpen = $action === 'save';
        $modalMode = $id > 0 ? 'edit' : 'create';
        $formData = $_POST;

        if ($action === 'delete') {
            if ($id <= 0) {
                $errors[] = 'Registro inválido para exclusão.';
            } else {
                $arquivoAtual = lei_repo_fetch_arquivo_atual($conn, $id);
                lei_repo_remove_file((string)($arquivoAtual['arquivo_caminho'] ?? ''));
                lei_repo_run_query($conn, 'DELETE FROM leis_repositorio WHERE id = ?', [$id]);
                $notice = 'Lei excluída com sucesso.';
                $modalOpen = false;
                if ($selectedLeiId === $id) {
                    $selectedLeiId = 0;
                }
            }
            goto lei_post_end;
        }

        if ($titulo === '') {
            $errors[] = 'Informe o título da lei.';
        }
        if (!in_array($tipoFonte, ['pdf_upload', 'link_pdf', 'link_externo'], true)) {
            $errors[] = 'Selecione um tipo de fonte válido.';
        }
        if (($tipoFonte === 'link_pdf' || $tipoFonte === 'link_externo') && $linkUrl === '') {
            $errors[] = 'Informe o link da lei.';
        }
        if ($linkUrl !== '' && filter_var($linkUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Informe uma URL válida.';
        }
        if ($tipoFonte === 'pdf_upload' && $id <= 0 && (int)($_FILES['arquivo_pdf']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Envie o PDF da lei.';
        }

        if (empty($errors)) {
            try {
                $fields = [
                    'titulo' => $titulo,
                    'descricao' => $descricao !== '' ? $descricao : null,
                    'tipo_fonte' => $tipoFonte,
                    'link_url' => ($tipoFonte === 'link_pdf' || $tipoFonte === 'link_externo') && $linkUrl !== '' ? $linkUrl : null,
                ];

                $registroId = $id;
                if ($id > 0) {
                    $setParts = [];
                    $params = [];
                    foreach ($fields as $column => $value) {
                        $setParts[] = "{$column} = ?";
                        $params[] = $value;
                    }
                    $params[] = $id;
                    $updated = lei_repo_run_query(
                        $conn,
                        'UPDATE leis_repositorio SET ' . implode(', ', $setParts) . ' WHERE id = ?',
                        $params
                    );
                    if ($updated === false) {
                        throw new RuntimeException('Falha ao atualizar a lei: ' . $conn->error);
                    }
                    $notice = 'Lei atualizada com sucesso.';
                } else {
                    $inserted = lei_repo_run_query(
                        $conn,
                        'INSERT INTO leis_repositorio (titulo, descricao, tipo_fonte, link_url, criado_por) VALUES (?, ?, ?, ?, ?)',
                        [$fields['titulo'], $fields['descricao'], $fields['tipo_fonte'], $fields['link_url'], $userMatricula > 0 ? $userMatricula : null]
                    );
                    if ($inserted === false) {
                        throw new RuntimeException('Falha ao criar a lei: ' . $conn->error);
                    }
                    $registroId = (int)$conn->insert_id;
                    $notice = 'Lei criada com sucesso.';
                }

                $arquivoAtual = lei_repo_fetch_arquivo_atual($conn, $registroId);
                if ($tipoFonte !== 'pdf_upload') {
                    if (!empty($arquivoAtual['arquivo_caminho'])) {
                        lei_repo_remove_file((string)$arquivoAtual['arquivo_caminho']);
                    }
                    lei_repo_run_query(
                        $conn,
                        'UPDATE leis_repositorio SET arquivo_caminho = NULL, arquivo_nome_original = NULL, arquivo_mime = NULL WHERE id = ?',
                        [$registroId]
                    );
                } else {
                    if ($removeArquivo === 1 && empty($_FILES['arquivo_pdf']['name'] ?? '')) {
                        lei_repo_remove_file((string)($arquivoAtual['arquivo_caminho'] ?? ''));
                        lei_repo_run_query(
                            $conn,
                            'UPDATE leis_repositorio SET arquivo_caminho = NULL, arquivo_nome_original = NULL, arquivo_mime = NULL WHERE id = ?',
                            [$registroId]
                        );
                    }
                    if (isset($_FILES['arquivo_pdf'])) {
                        $arquivoData = lei_repo_store_file($registroId, $_FILES['arquivo_pdf']);
                        if ($arquivoData !== []) {
                            lei_repo_remove_file((string)($arquivoAtual['arquivo_caminho'] ?? ''));
                            $saved = lei_repo_run_query(
                                $conn,
                                'UPDATE leis_repositorio SET arquivo_caminho = ?, arquivo_nome_original = ?, arquivo_mime = ? WHERE id = ?',
                                [$arquivoData['arquivo_caminho'], $arquivoData['arquivo_nome_original'], $arquivoData['arquivo_mime'], $registroId]
                            );
                            if ($saved === false) {
                                throw new RuntimeException('Falha ao salvar o PDF no registro.');
                            }
                        }
                    }
                }

                lei_repo_sync_tags($conn, $registroId, lei_repo_normalize_tags($tagsTexto), $userMatricula);
                $selectedLeiId = $registroId;
                $modalOpen = false;
            } catch (RuntimeException $e) {
                $notice = null;
                $errors[] = $e->getMessage();
                $modalOpen = true;
            }
        }
    }
}
lei_post_end:

if ($schemaOk) {
    $resTags = lei_repo_run_query($conn, 'SELECT id, nome, slug FROM leis_tags ORDER BY nome');
    if ($resTags instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($resTags)) {
            $allTags[] = $row;
        }
    }

    $where = [];
    $params = [];
    if ($searchTerm !== '') {
        $like = '%' . $searchTerm . '%';
        $where[] = '(
            l.titulo LIKE ?
            OR COALESCE(l.descricao, "") LIKE ?
            OR COALESCE(l.link_url, "") LIKE ?
            OR EXISTS (
                SELECT 1
                FROM leis_repositorio_tags lrt1
                INNER JOIN leis_tags lt1 ON lt1.id = lrt1.tag_id
                WHERE lrt1.lei_id = l.id
                  AND lt1.nome LIKE ?
            )
        )';
        array_push($params, $like, $like, $like, $like);
    }
    if ($tagTerm !== '') {
        $likeTag = '%' . $tagTerm . '%';
        $where[] = 'EXISTS (
            SELECT 1
            FROM leis_repositorio_tags lrt2
            INNER JOIN leis_tags lt2 ON lt2.id = lrt2.tag_id
            WHERE lrt2.lei_id = l.id
              AND lt2.nome LIKE ?
        )';
        $params[] = $likeTag;
    }

    $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
    $sqlLeis = "
        SELECT
            l.*,
            u.nome AS autor_nome,
            GROUP_CONCAT(DISTINCT lt.nome ORDER BY lt.nome SEPARATOR '||') AS tags_lista
        FROM leis_repositorio l
        LEFT JOIN usuarios u ON u.matricula = l.criado_por
        LEFT JOIN leis_repositorio_tags lrt ON lrt.lei_id = l.id
        LEFT JOIN leis_tags lt ON lt.id = lrt.tag_id
        {$whereSql}
        GROUP BY l.id
        ORDER BY l.atualizado_em DESC, l.id DESC
    ";
    $resLeis = lei_repo_run_query($conn, $sqlLeis, $params);
    if ($resLeis instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($resLeis)) {
            $row['tags_array'] = array_values(array_filter(array_map('trim', explode('||', (string)($row['tags_lista'] ?? '')))));
            $leis[] = $row;
        }
    } else {
        $errors[] = 'Não foi possível carregar as leis.';
    }
}

if ($selectedLeiId <= 0 && $leis !== []) {
    $selectedLeiId = (int)($leis[0]['id'] ?? 0);
}
foreach ($leis as $leiItem) {
    if ((int)$leiItem['id'] === $selectedLeiId) {
        $selectedLei = $leiItem;
        break;
    }
}

if (!$formData && $selectedLei) {
    $formData = [
        'id' => (string)$selectedLei['id'],
        'titulo' => (string)($selectedLei['titulo'] ?? ''),
        'descricao' => (string)($selectedLei['descricao'] ?? ''),
        'tipo_fonte' => (string)($selectedLei['tipo_fonte'] ?? 'link_externo'),
        'link_url' => (string)($selectedLei['link_url'] ?? ''),
        'tags_texto' => implode(', ', $selectedLei['tags_array'] ?? []),
        'remove_arquivo' => '',
        'arquivo_nome_original' => (string)($selectedLei['arquivo_nome_original'] ?? ''),
    ];
}

$previewUrl = '';
$previewType = '';
if ($selectedLei) {
    $tipoFonteAtual = (string)($selectedLei['tipo_fonte'] ?? '');
    if ($tipoFonteAtual === 'pdf_upload' && !empty($selectedLei['arquivo_caminho'])) {
        $previewUrl = 'actions/leis_arquivo.php?id=' . (int)$selectedLei['id'];
        $previewType = 'pdf';
    } elseif ($tipoFonteAtual === 'link_pdf' && !empty($selectedLei['link_url'])) {
        $previewUrl = (string)$selectedLei['link_url'];
        $previewType = 'pdf';
    } elseif ($tipoFonteAtual === 'link_externo' && !empty($selectedLei['link_url'])) {
        $previewUrl = (string)$selectedLei['link_url'];
        $previewType = 'iframe';
    }
}
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h3 class="mb-1 text-body-emphasis">Repositório de Leis</h3>
        <div class="text-muted">Cadastre leis por PDF ou link, organize por tags e visualize o conteúdo em tela.</div>
    </div>
    <?php if ($canManage && $schemaOk): ?>
        <button type="button" class="btn btn-primary" data-lei-open-create>
            <i class="bi bi-plus-lg me-1"></i>Nova lei
        </button>
    <?php endif; ?>
</div>

<?php if (!$schemaOk): ?>
    <div class="alert alert-warning">Schema do módulo de leis não encontrado. Execute <code>database/leis.sql</code>.</div>
    <?php return; ?>
<?php endif; ?>

<?php if ($notice): ?><div class="alert alert-success"><?= h_lei($notice) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= h_lei($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="get" action="app.php" class="row g-2 mb-3">
    <input type="hidden" name="page" value="leis">
    <div class="col-12 col-lg-5">
        <input type="text" class="form-control" name="q" value="<?= h_lei($searchTerm) ?>" placeholder="Buscar por título, descrição ou tag">
    </div>
    <div class="col-12 col-lg-3">
        <input type="text" class="form-control" name="tag" value="<?= h_lei($tagTerm) ?>" placeholder="Filtrar por tag">
    </div>
    <div class="col-12 col-lg-4 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
        <a class="btn btn-outline-secondary" href="app.php?page=leis">Limpar</a>
    </div>
</form>

<?php if ($allTags !== []): ?>
    <div class="mb-3 d-flex flex-wrap gap-2">
        <?php foreach ($allTags as $tag): ?>
            <a
                class="badge rounded-pill text-bg-light text-decoration-none border"
                href="app.php?page=leis&tag=<?= urlencode((string)$tag['nome']) ?>"
            >#<?= h_lei($tag['nome']) ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Leis cadastradas</h5>
                <?php if ($leis === []): ?>
                    <p class="text-muted mb-0">Nenhuma lei encontrada.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($leis as $lei): ?>
                            <a
                                href="app.php?page=leis&lei=<?= (int)$lei['id'] ?>&q=<?= urlencode($searchTerm) ?>&tag=<?= urlencode($tagTerm) ?>"
                                class="list-group-item list-group-item-action <?= (int)$lei['id'] === $selectedLeiId ? 'active' : '' ?>"
                            >
                                <div class="d-flex w-100 justify-content-between gap-3">
                                    <h6 class="mb-1"><?= h_lei($lei['titulo']) ?></h6>
                                    <small><?= h_lei(date('d/m/Y', strtotime((string)($lei['atualizado_em'] ?? 'now')))) ?></small>
                                </div>
                                <div class="small mb-2">
                                    <?= h_lei(match ((string)($lei['tipo_fonte'] ?? '')) {
                                        'pdf_upload' => 'PDF enviado',
                                        'link_pdf' => 'Link para PDF',
                                        default => 'Página externa',
                                    }) ?>
                                </div>
                                <?php if (!empty($lei['descricao'])): ?>
                                    <div class="small opacity-75"><?= h_lei(mb_strimwidth((string)$lei['descricao'], 0, 140, '...')) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($lei['tags_array'])): ?>
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        <?php foreach ($lei['tags_array'] as $tagNome): ?>
                                            <span class="badge rounded-pill text-bg-secondary">#<?= h_lei($tagNome) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column gap-3">
                <?php if (!$selectedLei): ?>
                    <div class="text-muted">Selecione uma lei para visualizar.</div>
                <?php else: ?>
                    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                        <div>
                            <h4 class="mb-1"><?= h_lei($selectedLei['titulo']) ?></h4>
                            <div class="text-muted small">
                                <?= h_lei(match ((string)($selectedLei['tipo_fonte'] ?? '')) {
                                    'pdf_upload' => 'PDF enviado ao sistema',
                                    'link_pdf' => 'Link para PDF externo',
                                    default => 'Página externa em iframe',
                                }) ?>
                                <?php if (!empty($selectedLei['autor_nome'])): ?>
                                    • cadastrado por <?= h_lei($selectedLei['autor_nome']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($canManage): ?>
                            <div class="d-flex gap-2">
                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    data-lei-open-edit
                                    data-id="<?= (int)$selectedLei['id'] ?>"
                                    data-titulo="<?= h_lei((string)($selectedLei['titulo'] ?? '')) ?>"
                                    data-descricao="<?= h_lei((string)($selectedLei['descricao'] ?? '')) ?>"
                                    data-tipo-fonte="<?= h_lei((string)($selectedLei['tipo_fonte'] ?? 'link_externo')) ?>"
                                    data-link-url="<?= h_lei((string)($selectedLei['link_url'] ?? '')) ?>"
                                    data-tags-texto="<?= h_lei(implode(', ', $selectedLei['tags_array'] ?? [])) ?>"
                                    data-arquivo-nome="<?= h_lei((string)($selectedLei['arquivo_nome_original'] ?? '')) ?>"
                                >Editar</button>
                                <form method="post" onsubmit="return confirm('Excluir esta lei?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$selectedLei['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Excluir</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($selectedLei['descricao'])): ?>
                        <div class="border rounded p-3 bg-body-tertiary">
                            <strong class="d-block mb-2">Descrição / termos de busca</strong>
                            <div><?= nl2br(h_lei((string)$selectedLei['descricao'])) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($selectedLei['tags_array'])): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($selectedLei['tags_array'] as $tagNome): ?>
                                <a class="badge rounded-pill text-bg-secondary text-decoration-none" href="app.php?page=leis&tag=<?= urlencode($tagNome) ?>">#<?= h_lei($tagNome) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($selectedLei['link_url'])): ?>
                        <div class="small">
                            <strong>Origem:</strong>
                            <a href="<?= h_lei((string)$selectedLei['link_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h_lei((string)$selectedLei['link_url']) ?></a>
                        </div>
                    <?php endif; ?>

                    <div class="border rounded overflow-hidden bg-body-tertiary" style="min-height:70vh">
                        <?php if ($previewUrl !== ''): ?>
                            <iframe
                                src="<?= h_lei($previewUrl) ?>"
                                title="Visualização da lei"
                                style="width:100%;height:70vh;border:0;background:#fff"
                            ></iframe>
                        <?php else: ?>
                            <div class="p-4 text-muted">
                                Não há arquivo ou link disponível para visualização.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="small text-muted">
                        Links externos podem bloquear visualização em <code>iframe</code> por política do próprio site. Nesses casos, abra o link diretamente.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
    <div class="modal fade" id="leiModal" tabindex="-1" aria-labelledby="leiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form method="post" id="leiForm" class="modal-content" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="leiModalLabel"><?= $modalMode === 'edit' ? 'Editar lei' : 'Nova lei' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="lei-id" value="<?= h_lei((string)($formData['id'] ?? '')) ?>">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="lei-titulo">Título</label>
                            <input type="text" class="form-control" name="titulo" id="lei-titulo" value="<?= h_lei((string)($formData['titulo'] ?? '')) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="lei-descricao">Descrição / termos para busca</label>
                            <textarea class="form-control" name="descricao" id="lei-descricao" rows="4"><?= h_lei((string)($formData['descricao'] ?? '')) ?></textarea>
                            <div class="form-text">Use esse campo para ementa, resumo e palavras-chave. A busca textual consulta esse conteúdo.</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="lei-tipo-fonte">Tipo de fonte</label>
                            <select class="form-select" name="tipo_fonte" id="lei-tipo-fonte">
                                <option value="pdf_upload" <?= (($formData['tipo_fonte'] ?? '') === 'pdf_upload') ? 'selected' : '' ?>>PDF enviado</option>
                                <option value="link_pdf" <?= (($formData['tipo_fonte'] ?? '') === 'link_pdf') ? 'selected' : '' ?>>Link para PDF</option>
                                <option value="link_externo" <?= (($formData['tipo_fonte'] ?? 'link_externo') === 'link_externo') ? 'selected' : '' ?>>Página externa</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-8" data-lei-link-group>
                            <label class="form-label" for="lei-link-url">Link</label>
                            <input type="url" class="form-control" name="link_url" id="lei-link-url" value="<?= h_lei((string)($formData['link_url'] ?? '')) ?>" placeholder="https://...">
                        </div>
                        <div class="col-12" data-lei-upload-group>
                            <label class="form-label" for="lei-arquivo-pdf">PDF da lei</label>
                            <input type="file" class="form-control" name="arquivo_pdf" id="lei-arquivo-pdf" accept=".pdf,application/pdf">
                            <div class="form-text">Arquivo PDF com até 20 MB.</div>
                        </div>
                        <?php if (!empty($formData['arquivo_nome_original'] ?? '')): ?>
                            <div class="col-12 small text-muted" data-lei-arquivo-atual>
                                Arquivo atual: <?= h_lei((string)$formData['arquivo_nome_original']) ?>
                            </div>
                            <div class="col-12 form-check" data-lei-remove-arquivo>
                                <input class="form-check-input" type="checkbox" name="remove_arquivo" id="lei-remove-arquivo" <?= !empty($formData['remove_arquivo']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="lei-remove-arquivo">Remover PDF atual</label>
                            </div>
                        <?php else: ?>
                            <div class="col-12 small text-muted d-none" data-lei-arquivo-atual></div>
                            <div class="col-12 form-check d-none" data-lei-remove-arquivo>
                                <input class="form-check-input" type="checkbox" name="remove_arquivo" id="lei-remove-arquivo">
                                <label class="form-check-label" for="lei-remove-arquivo">Remover PDF atual</label>
                            </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label" for="lei-tags-texto">Tags</label>
                            <input type="text" class="form-control" name="tags_texto" id="lei-tags-texto" value="<?= h_lei((string)($formData['tags_texto'] ?? '')) ?>" placeholder="educação infantil, inclusão, transporte">
                            <div class="form-text">Separe as tags por vírgula. Ao salvar, o sistema cria, remove e vincula as tags desse registro.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="lei-submit-button"><?= $modalMode === 'edit' ? 'Salvar alterações' : 'Criar lei' ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('leiModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    const modal = new bootstrap.Modal(modalEl);
    const titleEl = document.getElementById('leiModalLabel');
    const submitButton = document.getElementById('lei-submit-button');
    const tipoFonte = document.getElementById('lei-tipo-fonte');
    const linkGroup = document.querySelector('[data-lei-link-group]');
    const uploadGroup = document.querySelector('[data-lei-upload-group]');
    const arquivoAtual = document.querySelector('[data-lei-arquivo-atual]');
    const removeArquivoGroup = document.querySelector('[data-lei-remove-arquivo]');
    const removeArquivoInput = document.getElementById('lei-remove-arquivo');

    function setValue(id, value) {
        const field = document.getElementById(id);
        if (field) {
            field.value = value || '';
        }
    }

    function toggleSourceFields() {
        if (!tipoFonte || !linkGroup || !uploadGroup) {
            return;
        }
        const currentType = tipoFonte.value || 'link_externo';
        const uploadVisible = currentType === 'pdf_upload';
        linkGroup.classList.toggle('d-none', uploadVisible);
        uploadGroup.classList.toggle('d-none', !uploadVisible);
        if (removeArquivoGroup) {
            removeArquivoGroup.classList.toggle('d-none', !uploadVisible || !arquivoAtual || !arquivoAtual.textContent.trim());
        }
        if (arquivoAtual) {
            arquivoAtual.classList.toggle('d-none', !uploadVisible || !arquivoAtual.textContent.trim());
        }
        if (!uploadVisible && removeArquivoInput) {
            removeArquivoInput.checked = false;
        }
    }

    function fillModal(data, mode) {
        const state = Object.assign({
            id: '',
            titulo: '',
            descricao: '',
            tipo_fonte: 'link_externo',
            link_url: '',
            tags_texto: '',
            arquivo_nome_original: ''
        }, data || {});

        titleEl.textContent = mode === 'edit' ? 'Editar lei' : 'Nova lei';
        submitButton.textContent = mode === 'edit' ? 'Salvar alterações' : 'Criar lei';
        setValue('lei-id', state.id);
        setValue('lei-titulo', state.titulo);
        setValue('lei-descricao', state.descricao);
        setValue('lei-tipo-fonte', state.tipo_fonte);
        setValue('lei-link-url', state.link_url);
        setValue('lei-tags-texto', state.tags_texto);
        setValue('lei-arquivo-pdf', '');
        if (removeArquivoInput) {
            removeArquivoInput.checked = false;
        }
        if (arquivoAtual) {
            arquivoAtual.textContent = state.arquivo_nome_original ? ('Arquivo atual: ' + state.arquivo_nome_original) : '';
        }
        toggleSourceFields();
    }

    const createButton = document.querySelector('[data-lei-open-create]');
    if (createButton) {
        createButton.addEventListener('click', function () {
            fillModal({}, 'create');
            modal.show();
        });
    }

    document.querySelectorAll('[data-lei-open-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            fillModal({
                id: button.dataset.id || '',
                titulo: button.dataset.titulo || '',
                descricao: button.dataset.descricao || '',
                tipo_fonte: button.dataset.tipoFonte || 'link_externo',
                link_url: button.dataset.linkUrl || '',
                tags_texto: button.dataset.tagsTexto || '',
                arquivo_nome_original: button.dataset.arquivoNome || ''
            }, 'edit');
            modal.show();
        });
    });

    if (tipoFonte) {
        tipoFonte.addEventListener('change', toggleSourceFields);
        toggleSourceFields();
    }

    <?php if ($modalOpen): ?>
    fillModal({
        id: <?= json_encode((string)($formData['id'] ?? '')) ?>,
        titulo: <?= json_encode((string)($formData['titulo'] ?? '')) ?>,
        descricao: <?= json_encode((string)($formData['descricao'] ?? '')) ?>,
        tipo_fonte: <?= json_encode((string)($formData['tipo_fonte'] ?? 'link_externo')) ?>,
        link_url: <?= json_encode((string)($formData['link_url'] ?? '')) ?>,
        tags_texto: <?= json_encode((string)($formData['tags_texto'] ?? '')) ?>,
        arquivo_nome_original: <?= json_encode((string)($formData['arquivo_nome_original'] ?? '')) ?>
    }, <?= json_encode($modalMode) ?>);
    modal.show();
    <?php endif; ?>
});
</script>
