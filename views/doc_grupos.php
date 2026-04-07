<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../protocolo/group_helpers.php';

require_login();

if (!user_is_admin()) {
    ?>
    <div class="alert alert-danger mb-0">Sem permissão de acesso.</div>
    <?php
    return;
}

$conn = db();
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

function h_doc_group($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$schemaReady = proto_groups_schema_ready($conn);
$users = [];
$groups = [];
$editGroup = null;
$selectedMembers = [];

if ($schemaReady) {
    $result = $conn->query('SELECT matricula, nome FROM usuarios WHERE ativo = 1 ORDER BY nome');
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? 'save');
        $groupId = (int)($_POST['group_id'] ?? 0);

        try {
            if ($action === 'delete') {
                if ($groupId <= 0) {
                    throw new RuntimeException('Grupo inválido para exclusão.');
                }
                $conn->begin_transaction();
                $stmt = $conn->prepare('DELETE FROM doc_documento_grupos WHERE grupo_id = ?');
                $stmt->bind_param('i', $groupId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare('DELETE FROM doc_grupo_usuarios WHERE grupo_id = ?');
                $stmt->bind_param('i', $groupId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare('DELETE FROM doc_grupos WHERE id = ?');
                $stmt->bind_param('i', $groupId);
                $stmt->execute();
                $stmt->close();
                $conn->commit();

                $_SESSION['flash_success'] = 'Grupo excluído com sucesso.';
            } else {
                $nome = trim((string)($_POST['nome'] ?? ''));
                $descricao = trim((string)($_POST['descricao'] ?? ''));
                $ativo = !empty($_POST['ativo']) ? 1 : 0;
                $members = $_POST['membros'] ?? [];
                $members = is_array($members) ? array_values(array_unique(array_filter(array_map('intval', $members), static fn($id) => $id > 0))) : [];

                if ($nome === '') {
                    throw new RuntimeException('Informe o nome do grupo.');
                }

                $conn->begin_transaction();
                if ($groupId > 0) {
                    $stmt = $conn->prepare('UPDATE doc_grupos SET nome = ?, descricao = ?, ativo = ? WHERE id = ?');
                    $stmt->bind_param('ssii', $nome, $descricao, $ativo, $groupId);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $criadoPor = (int)($_SESSION['user']['matricula'] ?? 0);
                    $stmt = $conn->prepare('INSERT INTO doc_grupos (nome, descricao, ativo, criado_por) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('ssii', $nome, $descricao, $ativo, $criadoPor);
                    $stmt->execute();
                    $groupId = (int)$stmt->insert_id;
                    $stmt->close();
                }

                $stmt = $conn->prepare('DELETE FROM doc_grupo_usuarios WHERE grupo_id = ?');
                $stmt->bind_param('i', $groupId);
                $stmt->execute();
                $stmt->close();

                if (!empty($members)) {
                    $stmt = $conn->prepare('INSERT INTO doc_grupo_usuarios (grupo_id, usuario_matricula) VALUES (?, ?)');
                    foreach ($members as $memberId) {
                        $stmt->bind_param('ii', $groupId, $memberId);
                        $stmt->execute();
                    }
                    $stmt->close();
                }

                $conn->commit();
                $_SESSION['flash_success'] = $groupId > 0 ? 'Grupo salvo com sucesso.' : 'Grupo criado com sucesso.';
            }
        } catch (Throwable $e) {
            if ($conn->errno || $conn->sqlstate) {
                $conn->rollback();
            }
            $_SESSION['flash_error'] = 'Erro ao salvar grupo: ' . $e->getMessage();
        }

        header('Location: ' . url('app.php?page=doc_grupos'));
        exit;
    }

    $groupsSql = '
        SELECT g.*, COUNT(gu.usuario_matricula) AS total_membros
        FROM doc_grupos g
        LEFT JOIN doc_grupo_usuarios gu ON gu.grupo_id = g.id
        GROUP BY g.id, g.nome, g.descricao, g.ativo, g.criado_por, g.criado_em, g.atualizado_em
        ORDER BY g.nome
    ';
    $result = $conn->query($groupsSql);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }
    }

    $editId = (int)($_GET['edit'] ?? 0);
    if ($editId > 0) {
        $stmt = $conn->prepare('SELECT * FROM doc_grupos WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editGroup = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        if ($editGroup) {
            $stmt = $conn->prepare('SELECT usuario_matricula FROM doc_grupo_usuarios WHERE grupo_id = ?');
            $stmt->bind_param('i', $editId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $selectedMembers[] = (int)$row['usuario_matricula'];
            }
            $stmt->close();
        }
    }
}
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="mb-1 text-body-emphasis">Grupos de Destinatários</h3>
        <div class="text-muted">Cadastre grupos reutilizáveis para envio de documentos no protocolo.</div>
    </div>
    <a class="btn btn-outline-secondary" href="<?= h_doc_group(url('app.php?page=admin')) ?>">Voltar ao administrador</a>
</div>

<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= h_doc_group($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-danger"><?= h_doc_group($flashError) ?></div>
<?php endif; ?>

<?php if (!$schemaReady): ?>
    <div class="alert alert-warning">
        Para habilitar grupos de destinatários, crie as tabelas:
        <pre class="mb-0 mt-2 small"><code><?= h_doc_group(proto_groups_schema_sql()) ?></code></pre>
    </div>
    <?php return; ?>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Grupos cadastrados</h5>
                    <span class="text-muted small"><?= count($groups) ?> grupo(s)</span>
                </div>
                <?php if (empty($groups)): ?>
                    <div class="text-muted">Nenhum grupo cadastrado.</div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($groups as $group): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold"><?= h_doc_group($group['nome']) ?></div>
                                        <div class="small text-muted"><?= h_doc_group($group['descricao'] ?: 'Sem descrição') ?></div>
                                        <div class="small text-muted">
                                            <?= (int)$group['total_membros'] ?> membro(s)
                                            • <?= (int)$group['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= h_doc_group(url('app.php?page=doc_grupos&edit=' . (int)$group['id'])) ?>">Editar</a>
                                        <form method="post" onsubmit="return confirm('Excluir este grupo?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="mb-3"><?= $editGroup ? 'Editar grupo' : 'Novo grupo' ?></h5>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="group_id" value="<?= (int)($editGroup['id'] ?? 0) ?>">

                    <div class="row g-3">
                        <div class="col-12 col-lg-8">
                            <label class="form-label" for="grupo-nome">Nome do grupo</label>
                            <input
                                type="text"
                                class="form-control"
                                id="grupo-nome"
                                name="nome"
                                value="<?= h_doc_group($editGroup['nome'] ?? '') ?>"
                                required
                            >
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="ativo" id="grupo-ativo" <?= !isset($editGroup['ativo']) || (int)$editGroup['ativo'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="grupo-ativo">Grupo ativo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="grupo-descricao">Descrição</label>
                            <textarea class="form-control" id="grupo-descricao" name="descricao" rows="2"><?= h_doc_group($editGroup['descricao'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="grupo-search">Pesquisar usuários</label>
                            <input type="text" class="form-control" id="grupo-search" placeholder="Buscar por nome">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Membros do grupo</label>
                            <div class="border rounded p-3" style="max-height: 420px; overflow:auto;">
                                <?php foreach ($users as $user): ?>
                                    <div class="form-check grupo-membro-item" data-label="<?= h_doc_group($user['nome']) ?>">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="membros[]"
                                            value="<?= (int)$user['matricula'] ?>"
                                            id="membro-<?= (int)$user['matricula'] ?>"
                                            <?= in_array((int)$user['matricula'], $selectedMembers, true) ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label" for="membro-<?= (int)$user['matricula'] ?>">
                                            <?= h_doc_group($user['nome']) ?>
                                            <span class="text-muted small">(<?= (int)$user['matricula'] ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><?= $editGroup ? 'Salvar alterações' : 'Criar grupo' ?></button>
                            <a class="btn btn-outline-secondary" href="<?= h_doc_group(url('app.php?page=doc_grupos')) ?>">Limpar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('grupo-search');
    const items = Array.from(document.querySelectorAll('.grupo-membro-item'));
    if (!search) {
        return;
    }
    const normalize = function (value) {
        return (value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    };
    search.addEventListener('input', function () {
        const term = normalize(search.value.trim());
        items.forEach(function (item) {
            const label = normalize(item.dataset.label || '');
            item.style.display = label.includes(term) ? '' : 'none';
        });
    });
});
</script>
