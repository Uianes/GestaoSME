<?php
require 'conexao.php';

$pageTitle = "Órgãos";
ob_start();

// INSERT/UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_orgao') {
    $id_orgao   = $_POST['id_orgao'] ?? null;
    $nome_orgao = trim($_POST['nome_orgao']);

    if ($id_orgao) {
        $stmt = $pdo->prepare("UPDATE orgaos SET nome_orgao = ? WHERE id_orgao = ?");
        $stmt->execute([$nome_orgao, $id_orgao]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO orgaos (nome_orgao) VALUES (?)");
        $stmt->execute([$nome_orgao]);
    }

    header("Location: orgaos.php");
    exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM orgaos WHERE id_orgao = ?");
    $stmt->execute([$id]);
    header("Location: orgaos.php");
    exit;
}

// LISTA
$stmt = $pdo->query("SELECT * FROM orgaos ORDER BY id_orgao");
$orgaos = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0">Órgãos</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalOrgao">
        + Novo órgão
    </button>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th style="width: 80px;">ID</th>
                <th>Nome do órgão</th>
                <th class="text-end" style="width: 220px;">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orgaos as $orgao): ?>
            <tr>
                <td><?= $orgao['id_orgao'] ?></td>
                <td><?= htmlspecialchars($orgao['nome_orgao']) ?></td>
                <td class="text-end">
                    <a href="programas.php?id_orgao=<?= $orgao['id_orgao'] ?>" class="btn btn-sm btn-outline-secondary">
                        Ver
                    </a>
                    <button
                        class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#modalOrgao"
                        data-id="<?= $orgao['id_orgao'] ?>"
                        data-nome="<?= htmlspecialchars($orgao['nome_orgao'], ENT_QUOTES) ?>"
                    >
                        <i class="bi bi-pencil"></i>
                    </button>
                    <a href="orgaos.php?delete=<?= $orgao['id_orgao'] ?>"
                       class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Tem certeza que deseja excluir este órgão?')">
                        <i class="bi bi-trash"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Órgão -->
<div class="modal fade" id="modalOrgao" tabindex="-1" aria-labelledby="modalOrgaoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <div class="modal-header">
                <h5 class="modal-title" id="modalOrgaoLabel">Novo órgão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="acao" value="salvar_orgao">
                <input type="hidden" name="id_orgao" id="orgao_id">
                <div class="mb-3">
                    <label for="orgao_nome" class="form-label">Nome do órgão</label>
                    <input type="text" name="nome_orgao" id="orgao_nome" class="form-control" required>
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
const modalOrgao = document.getElementById('modalOrgao');
modalOrgao.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;
    const id   = button.getAttribute('data-id');
    const nome = button.getAttribute('data-nome');

    const modalTitle = modalOrgao.querySelector('.modal-title');
    const inputId    = document.getElementById('orgao_id');
    const inputNome  = document.getElementById('orgao_nome');

    if (id) {
        modalTitle.textContent = 'Editar órgão';
        inputId.value = id;
        inputNome.value = nome ?? '';
    } else {
        modalTitle.textContent = 'Novo órgão';
        inputId.value = '';
        inputNome.value = '';
    }
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
