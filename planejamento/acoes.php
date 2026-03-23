<?php
require 'conexao.php';

$id_programa = isset($_GET['id_programa']) ? (int) $_GET['id_programa'] : 0;
if ($id_programa <= 0) {
    header("Location: orgaos.php");
    exit;
}

// dados do programa + órgão
$sql = "SELECT p.*, o.nome_orgao, o.id_orgao
        FROM programa p
        INNER JOIN orgaos o ON o.id_orgao = p.id_orgao
        WHERE p.id_programa = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_programa]);
$programa = $stmt->fetch();

if (!$programa) {
    header("Location: orgaos.php");
    exit;
}

// INSERT/UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_acao') {
    // id da ação já existente (para saber se é edição)
    $id_acao_existente = $_POST['id_acao_existente'] ?? null;

    // código numérico digitado no formulário (será usado como id_acao no INSERT)
    $codigo_acao = $_POST['id_acao'] ?? null;
    $nome_acao = trim($_POST['nome_acao']);

    if ($id_acao_existente) {
        // EDIÇÃO: aqui, por segurança, só altero o nome da ação
        $stmt = $pdo->prepare("UPDATE acao SET nome_acao = ? WHERE id_acao = ?");
        $stmt->execute([$nome_acao, $id_acao_existente]);
    } else {
        // NOVO: insere usando o código digitado como id_acao
        $stmt = $pdo->prepare("INSERT INTO acao (id_acao, nome_acao, id_programa) VALUES (?, ?, ?)");
        $stmt->execute([$codigo_acao, $nome_acao, $id_programa]);
    }

    header("Location: acoes.php?id_programa={$id_programa}");
    exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM acao WHERE id_acao = ?");
    $stmt->execute([$id]);
    header("Location: acoes.php?id_programa={$id_programa}");
    exit;
}

// LISTA
$stmt = $pdo->prepare("SELECT * FROM acao WHERE id_programa = ? ORDER BY nome_acao");
$stmt->execute([$id_programa]);
$acoes = $stmt->fetchAll();

$pageTitle = "Ações - " . $programa['nome_programa'];
ob_start();
?>

<div class="mb-3 d-flex flex-wrap gap-2">
    <a href="programas.php?id_orgao=<?= $programa['id_orgao'] ?>" class="btn btn-sm btn-outline-secondary">
        &larr; Voltar para programas
    </a>
</div>

<h1 class="h4 mb-1">Ações do Programa</h1>
<p class="text-muted mb-3">
    <strong>Órgão:</strong> <?= htmlspecialchars($programa['nome_orgao']) ?><br>
    <strong>Programa:</strong> <?= htmlspecialchars($programa['nome_programa']) ?>
</p>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="h5 mb-0">Lista de ações</h2>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAcao">
        <!-- ícone opcional: lembre de incluir o CSS do Bootstrap Icons no layout se quiser -->
        <!-- <i class="bi bi-plus-circle"></i>  -->
        Nova ação
    </button>
</div>

<?php if (empty($acoes)): ?>
    <div class="alert alert-info">Nenhuma ação cadastrada para este programa.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Nome da ação</th>
                    <th class="text-end" style="width: 220px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($acoes as $acao): ?>
                    <tr>
                        <td><?= $acao['id_acao'] ?></td>
                        <td><?= htmlspecialchars($acao['nome_acao']) ?></td>
                        <td class="text-end">
                            <a href="metas.php?id_acao=<?= $acao['id_acao'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i> Ver Metas
                            </a>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAcao"
                                data-id="<?= $acao['id_acao'] ?>"
                                data-nome="<?= htmlspecialchars($acao['nome_acao'], ENT_QUOTES) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="acoes.php?id_programa=<?= $id_programa ?>&delete=<?= $acao['id_acao'] ?>"
                                class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir esta ação?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal Ação -->
<div class="modal fade" id="modalAcao" tabindex="-1" aria-labelledby="modalAcaoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAcaoLabel">Nova ação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="acao" value="salvar_acao">
                <!-- guarda o ID da ação já existente (para diferenciar novo/edição) -->
                <input type="hidden" name="id_acao_existente" id="acao_id_existente">

                <div class="mb-3">
                    <label for="acao_id" class="form-label">Código da ação</label>
                    <input type="number" name="id_acao" id="acao_id" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="acao_nome" class="form-label">Nome da ação</label>
                    <input type="text" name="nome_acao" id="acao_nome" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" type="submit">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modalAcao = document.getElementById('modalAcao');
    modalAcao.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;

        const id = button.getAttribute('data-id');
        const nome = button.getAttribute('data-nome');

        const modalTitle = modalAcao.querySelector('.modal-title');
        const inputId = document.getElementById('acao_id');
        const inputIdExist = document.getElementById('acao_id_existente');
        const inputNome = document.getElementById('acao_nome');

        if (id) {
            // edição
            modalTitle.textContent = 'Editar ação';
            inputIdExist.value = id;     // usado no UPDATE (WHERE id_acao = ?)
            inputId.value = id;     // mostra o código atual
            inputId.readOnly = true;   // evita mudar o código e quebrar vínculos
            inputNome.value = nome ?? '';
        } else {
            // nova
            modalTitle.textContent = 'Nova ação';
            inputIdExist.value = '';
            inputId.value = '';
            inputId.readOnly = false;
            inputNome.value = '';
        }
    });
</script>

<?php
$content = ob_get_clean();
include 'layout.php';