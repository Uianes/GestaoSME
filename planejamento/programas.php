<?php
require 'conexao.php';

$id_orgao = isset($_GET['id_orgao']) ? (int) $_GET['id_orgao'] : 0;
if ($id_orgao <= 0) {
    header("Location: orgaos.php");
    exit;
}

// dados do órgão
$stmt = $pdo->prepare("SELECT * FROM orgaos WHERE id_orgao = ?");
$stmt->execute([$id_orgao]);
$orgao = $stmt->fetch();
if (!$orgao) {
    header("Location: orgaos.php");
    exit;
}

// INSERT/UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_programa') {
    $id_programa   = $_POST['id_programa'] ?? null;
    $nome_programa = trim($_POST['nome_programa']);
    $objetivo      = trim($_POST['objetivo']);

    if ($id_programa) {
        $stmt = $pdo->prepare("UPDATE programa SET nome_programa = ?, objetivo = ? WHERE id_programa = ?");
        $stmt->execute([$nome_programa, $objetivo, $id_programa]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO programa (nome_programa, id_orgao, objetivo) VALUES (?, ?, ?)");
        $stmt->execute([$nome_programa, $id_orgao, $objetivo]);
    }

    header("Location: programas.php?id_orgao={$id_orgao}");
    exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM programa WHERE id_programa = ?");
    $stmt->execute([$id]);
    header("Location: programas.php?id_orgao={$id_orgao}");
    exit;
}

// LISTA
$stmt = $pdo->prepare("SELECT * FROM programa WHERE id_orgao = ? ORDER BY nome_programa");
$stmt->execute([$id_orgao]);
$programas = $stmt->fetchAll();

$pageTitle = "Programas - " . $orgao['nome_orgao'];
ob_start();
?>

<div class="mb-3">
    <a href="orgaos.php" class="btn btn-sm btn-outline-secondary">&larr; Voltar para órgãos</a>
</div>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0">
        Programas de: <span class="fw-bold"><?= htmlspecialchars($orgao['nome_orgao']) ?></span>
    </h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPrograma">
        + Novo programa
    </button>
</div>

<?php if (empty($programas)): ?>
    <div class="alert alert-info">Nenhum programa cadastrado para este órgão.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($programas as $programa): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?= htmlspecialchars($programa['nome_programa']) ?></h5>
                        <p class="card-text small text-muted flex-grow-1" style="white-space: pre-line;">
                            <?= nl2br(htmlspecialchars($programa['objetivo'])) ?>
                        </p>
                        <div class="mt-3 d-flex justify-content-between flex-wrap gap-2">
                            <a href="acoes.php?id_programa=<?= $programa['id_programa'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i> Ver ações
                            </a>
                            <div class="btn-group btn-group-sm">
                                <button
                                    class="btn btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalPrograma"
                                    data-id="<?= $programa['id_programa'] ?>"
                                    data-nome="<?= htmlspecialchars($programa['nome_programa'], ENT_QUOTES) ?>"
                                    data-objetivo="<?= htmlspecialchars($programa['objetivo'], ENT_QUOTES) ?>"
                                >
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="programas.php?id_orgao=<?= $id_orgao ?>&delete=<?= $programa['id_programa'] ?>"
                                   class="btn btn-outline-danger"
                                   onclick="return confirm('Excluir este programa?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal Programa -->
<div class="modal fade" id="modalPrograma" tabindex="-1" aria-labelledby="modalProgramaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="post">
            <div class="modal-header">
                <h5 class="modal-title" id="modalProgramaLabel">Novo programa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="acao" value="salvar_programa">
                <input type="hidden" name="id_programa" id="programa_id">
                <div class="mb-3">
                    <label for="programa_nome" class="form-label">Nome do programa</label>
                    <input type="text" name="nome_programa" id="programa_nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="programa_objetivo" class="form-label">Objetivo</label>
                    <textarea name="objetivo" id="programa_objetivo" rows="4" class="form-control" required></textarea>
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
const modalPrograma = document.getElementById('modalPrograma');
modalPrograma.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;
    const id        = button.getAttribute('data-id');
    const nome      = button.getAttribute('data-nome');
    const objetivo  = button.getAttribute('data-objetivo');

    const modalTitle       = modalPrograma.querySelector('.modal-title');
    const inputId          = document.getElementById('programa_id');
    const inputNome        = document.getElementById('programa_nome');
    const textareaObjetivo = document.getElementById('programa_objetivo');

    if (id) {
        modalTitle.textContent = 'Editar programa';
        inputId.value          = id;
        inputNome.value        = nome ?? '';
        textareaObjetivo.value = objetivo ?? '';
    } else {
        modalTitle.textContent = 'Novo programa';
        inputId.value          = '';
        inputNome.value        = '';
        textareaObjetivo.value = '';
    }
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
