<?php
require 'conexao.php';
require_once __DIR__ . '/calendar_sync.php';

$id_acao = isset($_GET['id_acao']) ? (int) $_GET['id_acao'] : 0;
if ($id_acao <= 0) {
    header("Location: orgaos.php");
    exit;
}

$sql = "SELECT a.*, p.nome_programa, p.id_programa, o.nome_orgao, o.id_orgao
        FROM acao a
        INNER JOIN programa p ON p.id_programa = a.id_programa
        INNER JOIN orgaos o ON o.id_orgao = p.id_orgao
        WHERE a.id_acao = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_acao]);
$acao = $stmt->fetch();

if (!$acao) {
    header("Location: orgaos.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_meta') {
    $id_meta = isset($_POST['id_meta']) && $_POST['id_meta'] !== '' ? (int)$_POST['id_meta'] : null;
    $indicador = trim((string)($_POST['indicador'] ?? ''));
    $anoRef = (string)($_POST['ano_ref'] ?? '');
    $valorRef = trim((string)($_POST['valor_ref'] ?? ''));
    $unRef = trim((string)($_POST['un_ref'] ?? ''));
    $meta2026 = trim((string)($_POST['meta_2026'] ?? ''));
    $meta2027 = trim((string)($_POST['meta_2027'] ?? ''));
    $meta2028 = trim((string)($_POST['meta_2028'] ?? ''));
    $meta2029 = trim((string)($_POST['meta_2029'] ?? ''));

    if ($id_meta !== null) {
        $sql = "UPDATE meta
                SET indicador = ?, ano_ref = ?, valor_ref = ?, un_ref = ?,
                    `2026` = ?, `2027` = ?, `2028` = ?, `2029` = ?
                WHERE id_meta = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$indicador, $anoRef, $valorRef, $unRef, $meta2026, $meta2027, $meta2028, $meta2029, $id_meta]);
    } else {
        $sql = "INSERT INTO meta (indicador, ano_ref, valor_ref, un_ref, `2026`, `2027`, `2028`, `2029`, id_acao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$indicador, $anoRef, $valorRef, $unRef, $meta2026, $meta2027, $meta2028, $meta2029, $id_acao]);
    }

    header("Location: metas.php?id_acao={$id_acao}");
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id_iniciativa FROM iniciativa WHERE id_meta = ?");
        $stmt->execute([$id]);
        $iniciativaIds = array_map('intval', array_column($stmt->fetchAll(), 'id_iniciativa'));

        if (!empty($iniciativaIds)) {
            foreach ($iniciativaIds as $idIniciativa) {
                planejamento_calendar_delete_iniciativa($pdo, $idIniciativa);
            }

            $placeholders = implode(',', array_fill(0, count($iniciativaIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM dotacoes WHERE id_iniciativa IN ($placeholders)");
            $stmt->execute($iniciativaIds);

            $stmt = $pdo->prepare("DELETE FROM iniciativa WHERE id_iniciativa IN ($placeholders)");
            $stmt->execute($iniciativaIds);
        }

        $stmt = $pdo->prepare("DELETE FROM meta WHERE id_meta = ?");
        $stmt->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    header("Location: metas.php?id_acao={$id_acao}");
    exit;
}

$sql = "SELECT m.*,
               COUNT(i.id_iniciativa) AS total_iniciativas
        FROM meta m
        LEFT JOIN iniciativa i ON i.id_meta = m.id_meta
        WHERE m.id_acao = ?
        GROUP BY m.id_meta
        ORDER BY m.id_meta DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_acao]);
$metas = $stmt->fetchAll();

$pageTitle = "Metas - " . $acao['nome_acao'];
ob_start();
?>

<div class="mb-3 d-flex flex-wrap gap-2">
    <a href="acoes.php?id_programa=<?= (int)$acao['id_programa'] ?>" class="btn btn-sm btn-outline-secondary">
        &larr; Voltar para ações
    </a>
</div>

<h1 class="h4 mb-1">Metas da Ação #<?= (int)$acao['id_acao'] ?></h1>
<p class="text-muted mb-3">
    <strong>Órgão:</strong> <?= htmlspecialchars($acao['nome_orgao']) ?><br>
    <strong>Programa:</strong> <?= htmlspecialchars($acao['nome_programa']) ?><br>
    <strong>Ação:</strong> <?= htmlspecialchars($acao['nome_acao']) ?>
</p>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="h5 mb-0">Lista de metas</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMeta">
        + Nova meta
    </button>
</div>

<?php if (empty($metas)): ?>
    <div class="alert alert-info">Nenhuma meta cadastrada para esta ação.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Indicador</th>
                    <th>Ano ref.</th>
                    <th>Valor ref.</th>
                    <th>Metas 2026-2029</th>
                    <th class="text-center">Iniciativas</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($metas as $meta): ?>
                    <tr>
                        <td><?= (int)$meta['id_meta'] ?></td>
                        <td style="min-width: 320px;">
                            <?= nl2br(htmlspecialchars($meta['indicador'], ENT_QUOTES, 'UTF-8')) ?>
                        </td>
                        <td><?= htmlspecialchars((string)$meta['ano_ref'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <strong><?= htmlspecialchars($meta['valor_ref'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <br>
                            <small class="text-muted"><?= htmlspecialchars($meta['un_ref'], ENT_QUOTES, 'UTF-8') ?></small>
                        </td>
                        <td class="small">
                            <div><strong>2026:</strong> <?= htmlspecialchars($meta['2026'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div><strong>2027:</strong> <?= htmlspecialchars($meta['2027'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div><strong>2028:</strong> <?= htmlspecialchars($meta['2028'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div><strong>2029:</strong> <?= htmlspecialchars($meta['2029'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge text-bg-secondary"><?= (int)$meta['total_iniciativas'] ?></span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="iniciativas.php?id_meta=<?= (int)$meta['id_meta'] ?>" class="btn btn-outline-secondary">
                                    Iniciativas
                                </a>
                                <button
                                    class="btn btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalMeta"
                                    data-id="<?= (int)$meta['id_meta'] ?>"
                                    data-indicador="<?= htmlspecialchars($meta['indicador'], ENT_QUOTES) ?>"
                                    data-ano-ref="<?= htmlspecialchars((string)$meta['ano_ref'], ENT_QUOTES) ?>"
                                    data-valor-ref="<?= htmlspecialchars($meta['valor_ref'], ENT_QUOTES) ?>"
                                    data-un-ref="<?= htmlspecialchars($meta['un_ref'], ENT_QUOTES) ?>"
                                    data-meta-2026="<?= htmlspecialchars($meta['2026'], ENT_QUOTES) ?>"
                                    data-meta-2027="<?= htmlspecialchars($meta['2027'], ENT_QUOTES) ?>"
                                    data-meta-2028="<?= htmlspecialchars($meta['2028'], ENT_QUOTES) ?>"
                                    data-meta-2029="<?= htmlspecialchars($meta['2029'], ENT_QUOTES) ?>"
                                >
                                    Editar
                                </button>
                                <a href="metas.php?id_acao=<?= (int)$id_acao ?>&delete=<?= (int)$meta['id_meta'] ?>"
                                   class="btn btn-outline-danger"
                                   onclick="return confirm('Excluir esta meta e suas iniciativas?')">
                                    Excluir
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="modal fade" id="modalMeta" tabindex="-1" aria-labelledby="modalMetaLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form class="modal-content" method="post">
            <div class="modal-header">
                <h5 class="modal-title" id="modalMetaLabel">Nova meta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="acao" value="salvar_meta">
                <input type="hidden" name="id_meta" id="meta_id">

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Indicador</label>
                        <textarea name="indicador" id="meta_indicador" rows="3" class="form-control" required></textarea>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Ano de referência</label>
                        <input type="number" name="ano_ref" id="meta_ano_ref" class="form-control" min="2000" max="2099" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Valor de referência</label>
                        <input type="text" name="valor_ref" id="meta_valor_ref" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Unidade de referência</label>
                        <input type="text" name="un_ref" id="meta_un_ref" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Meta 2026</label>
                        <input type="text" name="meta_2026" id="meta_2026" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Meta 2027</label>
                        <input type="text" name="meta_2027" id="meta_2027" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Meta 2028</label>
                        <input type="text" name="meta_2028" id="meta_2028" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Meta 2029</label>
                        <input type="text" name="meta_2029" id="meta_2029" class="form-control" required>
                    </div>
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
const modalMeta = document.getElementById('modalMeta');
modalMeta.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;
    const titulo = modalMeta.querySelector('.modal-title');
    const id = button ? button.getAttribute('data-id') : '';

    document.getElementById('meta_id').value = id ?? '';
    document.getElementById('meta_indicador').value = button ? (button.getAttribute('data-indicador') ?? '') : '';
    document.getElementById('meta_ano_ref').value = button ? (button.getAttribute('data-ano-ref') ?? '') : '';
    document.getElementById('meta_valor_ref').value = button ? (button.getAttribute('data-valor-ref') ?? '') : '';
    document.getElementById('meta_un_ref').value = button ? (button.getAttribute('data-un-ref') ?? '') : '';
    document.getElementById('meta_2026').value = button ? (button.getAttribute('data-meta-2026') ?? '') : '';
    document.getElementById('meta_2027').value = button ? (button.getAttribute('data-meta-2027') ?? '') : '';
    document.getElementById('meta_2028').value = button ? (button.getAttribute('data-meta-2028') ?? '') : '';
    document.getElementById('meta_2029').value = button ? (button.getAttribute('data-meta-2029') ?? '') : '';

    titulo.textContent = id ? 'Editar meta' : 'Nova meta';
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
