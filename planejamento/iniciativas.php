<?php
require 'conexao.php';
require_once __DIR__ . '/calendar_sync.php';

$id_meta = isset($_GET['id_meta']) ? (int) $_GET['id_meta'] : 0;
if ($id_meta <= 0) {
    header("Location: orgaos.php");
    exit;
}

// dados da meta + ação + programa + órgão
$sql = "SELECT m.*, 
               a.nome_acao, a.id_acao,
               p.nome_programa, p.id_programa,
               o.nome_orgao, o.id_orgao
        FROM meta m
        INNER JOIN acao a     ON a.id_acao     = m.id_acao
        INNER JOIN programa p ON p.id_programa = a.id_programa
        INNER JOIN orgaos o   ON o.id_orgao    = p.id_orgao
        WHERE m.id_meta = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_meta]);
$meta = $stmt->fetch();

if (!$meta) {
    header("Location: orgaos.php");
    exit;
}

// ----------------------
// HANDLER: TOGGLE PASSO
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'toggle_passo') {
    $id_passo = (int) ($_POST['id_passo'] ?? 0);
    if ($id_passo > 0) {
        // inverte o status concluido (0 -> 1, 1 -> 0)
        $sql = "UPDATE iniciativa_passo 
                   SET concluido = IF(concluido = 1, 0, 1)
                 WHERE id_passo = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_passo]);
    }

    header("Location: iniciativas.php?id_meta={$id_meta}");
    exit;
}

// --------------------------------------
// HANDLER: SALVAR INICIATIVA + DOTAÇÕES
// --------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_iniciativa') {
    $id_iniciativa = isset($_POST['id_iniciativa']) && $_POST['id_iniciativa'] !== '' ? (int)$_POST['id_iniciativa'] : null;

    $Oque          = trim((string)($_POST['Oque'] ?? ''));
    $Onde          = trim((string)($_POST['Onde'] ?? ''));
    $Quando        = trim((string)($_POST['Quando'] ?? ''));
    $Como          = (string)($_POST['Como'] ?? '');
    $Quem          = trim((string)($_POST['Quem'] ?? ''));
    $Quanto        = trim((string)($_POST['Quanto'] ?? ''));
    $Justificativa = trim((string)($_POST['Justificativa'] ?? ''));

    $pdo->beginTransaction();
    try {
        if ($id_iniciativa) {
            $sql = "UPDATE iniciativa
                    SET Oque = ?, Onde = ?, Quando = ?, Como = ?, Quem = ?, Quanto = ?, Justificativa = ?
                    WHERE id_iniciativa = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$Oque, $Onde, $Quando, $Como, $Quem, $Quanto, $Justificativa, $id_iniciativa]);
        } else {
            $sql = "INSERT INTO iniciativa (Oque, Onde, Quando, Como, Quem, Quanto, Justificativa, id_meta)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$Oque, $Onde, $Quando, $Como, $Quem, $Quanto, $Justificativa, $id_meta]);
            $id_iniciativa = (int)$pdo->lastInsertId();
        }

        $stmtDel = $pdo->prepare("DELETE FROM iniciativa_passo WHERE id_iniciativa = ?");
        $stmtDel->execute([$id_iniciativa]);

        $linhas = preg_split("/\r\n|\n|\r/", (string)$Como);
        $ordem  = 1;
        $stmtPasso = $pdo->prepare(
            "INSERT INTO iniciativa_passo (id_iniciativa, descricao, concluido, ordem)
             VALUES (?, ?, 0, ?)"
        );

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha !== '') {
                $stmtPasso->execute([$id_iniciativa, $linha, $ordem]);
                $ordem++;
            }
        }

        $stmt = $pdo->prepare("DELETE FROM dotacoes WHERE id_iniciativa = ?");
        $stmt->execute([$id_iniciativa]);

        if (!empty($_POST['dotacao']) && is_array($_POST['dotacao'])) {
            $stmt = $pdo->prepare("INSERT INTO dotacoes (id_iniciativa, dotacao) VALUES (?, ?)");
            foreach ($_POST['dotacao'] as $dot) {
                $dot = trim((string)$dot);
                if ($dot !== '') {
                    $stmt->execute([$id_iniciativa, $dot]);
                }
            }
        }

        planejamento_calendar_upsert_iniciativa(
            $pdo,
            [
                'id_iniciativa' => $id_iniciativa,
                'id_meta' => $meta['id_meta'],
                'indicador' => $meta['indicador'],
                'nome_orgao' => $meta['nome_orgao'],
                'nome_programa' => $meta['nome_programa'],
                'nome_acao' => $meta['nome_acao'],
                'Oque' => $Oque,
                'Onde' => $Onde,
                'Quando' => $Quando,
                'Quem' => $Quem,
                'Quanto' => $Quanto,
                'Justificativa' => $Justificativa,
            ],
            (int)($_SESSION['user']['matricula'] ?? 0)
        );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    header("Location: iniciativas.php?id_meta={$id_meta}");
    exit;
}

// DELETE INICIATIVA
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $pdo->beginTransaction();
    try {
        planejamento_calendar_delete_iniciativa($pdo, $id);
        $stmt = $pdo->prepare("DELETE FROM dotacoes WHERE id_iniciativa = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM iniciativa WHERE id_iniciativa = ?");
        $stmt->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    header("Location: iniciativas.php?id_meta={$id_meta}");
    exit;
}

// LISTA INICIATIVAS
$sql = "SELECT i.*,
               GROUP_CONCAT(d.dotacao ORDER BY d.dotacao SEPARATOR ', ') AS dotacoes
        FROM iniciativa i
        LEFT JOIN dotacoes d ON d.id_iniciativa = i.id_iniciativa
        WHERE i.id_meta = ?
        GROUP BY i.id_iniciativa
        ORDER BY i.id_iniciativa DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_meta]);
$iniciativas = $stmt->fetchAll();

// Busca todos os PASSOS de todas as iniciativas de uma vez
$passosPorIniciativa = [];
if (!empty($iniciativas)) {
    $ids = array_column($iniciativas, 'id_iniciativa');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sqlPassos = "SELECT * FROM iniciativa_passo 
                  WHERE id_iniciativa IN ($placeholders)
                  ORDER BY id_iniciativa, ordem, id_passo";
    $stmtPassos = $pdo->prepare($sqlPassos);
    $stmtPassos->execute($ids);
    while ($p = $stmtPassos->fetch()) {
        $passosPorIniciativa[$p['id_iniciativa']][] = $p;
    }
}

$pageTitle = "Iniciativas - Meta " . $meta['id_meta'];
ob_start();
?>

<div class="mb-3 d-flex flex-wrap gap-2">
    <a href="metas.php?id_acao=<?= $meta['id_acao'] ?>" class="btn btn-sm btn-outline-secondary">
        &larr; Voltar para metas
    </a>
</div>

<h1 class="h4 mb-1">Iniciativas da Meta #<?= $meta['id_meta'] ?></h1>
<p class="text-muted mb-3">
    <strong>Órgão:</strong> <?= htmlspecialchars($meta['nome_orgao']) ?><br>
    <strong>Programa:</strong> <?= htmlspecialchars($meta['nome_programa']) ?><br>
    <strong>Ação:</strong> <?= htmlspecialchars($meta['nome_acao']) ?><br>
    <strong>Ano ref. da meta:</strong> <?= htmlspecialchars($meta['ano_ref']) ?> |
    <strong>Valor ref.:</strong> <?= htmlspecialchars($meta['valor_ref']) . ' ' . htmlspecialchars($meta['un_ref']) ?>
</p>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="h5 mb-0">Lista de iniciativas</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalIniciativa">
        + Nova iniciativa
    </button>
</div>

<?php if (empty($iniciativas)): ?>
    <div class="alert alert-info">Nenhuma iniciativa cadastrada para esta meta.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($iniciativas as $ini): 
            $passos = $passosPorIniciativa[$ini['id_iniciativa']] ?? [];
        ?>
            <div class="col-12">
                <div class="card shadow-sm" id="ini-<?= (int)$ini['id_iniciativa'] ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <h5 class="card-title mb-1"><?= htmlspecialchars($ini['Oque']) ?></h5>
                            <div class="btn-group btn-group-sm">
                                <button
                                    class="btn btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalIniciativa"
                                    data-id="<?= $ini['id_iniciativa'] ?>"
                                    data-oque="<?= htmlspecialchars($ini['Oque'], ENT_QUOTES) ?>"
                                    data-onde="<?= htmlspecialchars($ini['Onde'], ENT_QUOTES) ?>"
                                    data-quando="<?= htmlspecialchars($ini['Quando'], ENT_QUOTES) ?>"
                                    data-como="<?= htmlspecialchars($ini['Como'], ENT_QUOTES) ?>"
                                    data-quem="<?= htmlspecialchars($ini['Quem'], ENT_QUOTES) ?>"
                                    data-quanto="<?= htmlspecialchars($ini['Quanto'], ENT_QUOTES) ?>"
                                    data-justificativa="<?= htmlspecialchars($ini['Justificativa'], ENT_QUOTES) ?>"
                                    data-dotacoes="<?= htmlspecialchars($ini['dotacoes'] ?? '', ENT_QUOTES) ?>"
                                >
                                    Editar
                                </button>
                                <a href="iniciativas.php?id_meta=<?= $id_meta ?>&delete=<?= $ini['id_iniciativa'] ?>"
                                   class="btn btn-outline-danger"
                                   onclick="return confirm('Excluir esta iniciativa e suas dotações?')">
                                    Excluir
                                </a>
                            </div>
                        </div>

                        <div class="row mt-2 small">
                            <div class="col-12 col-md-4">
                                <strong>Onde:</strong> <?= htmlspecialchars($ini['Onde']) ?>
                            </div>
                            <div class="col-6 col-md-3">
                                <strong>Quando:</strong> <?= htmlspecialchars($ini['Quando']) ?>
                            </div>
                            <div class="col-6 col-md-3">
                                <strong>Quem:</strong> <?= htmlspecialchars($ini['Quem']) ?>
                            </div>
                            <div class="col-6 col-md-2">
                                <strong>Quanto:</strong> <?= htmlspecialchars($ini['Quanto']) ?>
                            </div>
                        </div>

                        <div class="mt-2">
                            <strong>Como (checklist):</strong>
                            <?php if (!empty($passos)): ?>
                                <ul class="list-unstyled small mb-0 mt-1">
                                    <?php foreach ($passos as $passo): 
                                        $checked = $passo['concluido'] ? 'checked' : '';
                                        $class   = $passo['concluido']
                                            ? 'text-decoration-line-through text-muted'
                                            : '';
                                    ?>
                                        <li class="mb-1">
                                            <form method="post" class="d-flex align-items-start gap-2">
                                                <input type="hidden" name="acao" value="toggle_passo">
                                                <input type="hidden" name="id_passo" value="<?= $passo['id_passo'] ?>">
                                                <input type="checkbox"
                                                       class="form-check-input mt-1"
                                                       <?= $checked ?>
                                                       onclick="this.form.submit();">
                                                <span class="form-check-label <?= $class ?>">
                                                    <?= htmlspecialchars($passo['descricao']) ?>
                                                </span>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span class="small text-muted">
                                    Nenhum passo cadastrado. Edite a iniciativa e preencha o campo "Como" com um passo por linha.
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-2">
                            <strong>Justificativa:</strong>
                            <span class="small"><?= nl2br(htmlspecialchars($ini['Justificativa'])) ?></span>
                        </div>

                        <div class="mt-3">
                            <strong>Dotações:</strong>
                            <?php if ($ini['dotacoes']): ?>
                                <span class="badge text-bg-secondary"><?= htmlspecialchars($ini['dotacoes']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">Nenhuma dotação vinculada.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal Iniciativa -->
<div class="modal fade" id="modalIniciativa" tabindex="-1" aria-labelledby="modalIniciativaLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form class="modal-content" method="post">
            <div class="modal-header">
                <h5 class="modal-title" id="modalIniciativaLabel">Nova iniciativa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="acao" value="salvar_iniciativa">
                <input type="hidden" name="id_iniciativa" id="ini_id">

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">O quê (descrição da iniciativa)</label>
                        <input type="text" name="Oque" id="ini_oque" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Onde</label>
                        <input type="text" name="Onde" id="ini_onde" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Quando</label>
                        <input type="date" name="Quando" id="ini_quando" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Quem</label>
                        <input type="text" name="Quem" id="ini_quem" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Quanto (valor estimado)</label>
                        <input type="text" name="Quanto" id="ini_quanto" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label">
                            Como (estratégia de execução)
                            <small class="text-muted">(um passo por linha — vira checklist)</small>
                        </label>
                        <textarea name="Como" id="ini_como" rows="3" class="form-control" required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Justificativa</label>
                        <textarea name="Justificativa" id="ini_justificativa" rows="3" class="form-control" required></textarea>
                    </div>

                    <div class="col-12">
                        <hr>
                        <h6>Dotações orçamentárias</h6>
                        <p class="text-muted small mb-2">
                            Uma iniciativa pode ter várias dotações. Informe uma por linha (apenas números).
                        </p>

                        <div id="lista-dotacoes"></div>
                        <button class="btn btn-sm btn-outline-primary" type="button" onclick="adicionarDotacao()">
                            + Adicionar dotação
                        </button>
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
function adicionarDotacao(valor = '') {
    const container = document.getElementById('lista-dotacoes');
    const div = document.createElement('div');
    div.className = 'input-group mb-2 dotacao-item';
    div.innerHTML = `
        <input type="number" name="dotacao[]" class="form-control"
               placeholder="Dotação orçamentária" value="${valor}"
               step="1" min="0">
        <button class="btn btn-outline-secondary" type="button" onclick="removerDotacao(this)">-</button>
    `;
    container.appendChild(div);
}

function removerDotacao(btn) {
    const item = btn.closest('.dotacao-item');
    if (item) item.remove();
}

const modalIniciativa = document.getElementById('modalIniciativa');
modalIniciativa.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;

    const id            = button.getAttribute('data-id');
    const oque          = button.getAttribute('data-oque');
    const onde          = button.getAttribute('data-onde');
    const quando        = button.getAttribute('data-quando');
    const como          = button.getAttribute('data-como');
    const quem          = button.getAttribute('data-quem');
    const quanto        = button.getAttribute('data-quanto');
    const justificativa = button.getAttribute('data-justificativa');
    const dotacoesStr   = button.getAttribute('data-dotacoes');

    const titulo        = modalIniciativa.querySelector('.modal-title');
    const fId           = document.getElementById('ini_id');
    const fOque         = document.getElementById('ini_oque');
    const fOnde         = document.getElementById('ini_onde');
    const fQuando       = document.getElementById('ini_quando');
    const fQuem         = document.getElementById('ini_quem');
    const fQuanto       = document.getElementById('ini_quanto');
    const fComo         = document.getElementById('ini_como');
    const fJustificativa= document.getElementById('ini_justificativa');
    const listaDotacoes = document.getElementById('lista-dotacoes');

    listaDotacoes.innerHTML = '';

    if (id) {
        titulo.textContent = 'Editar iniciativa';
        fId.value            = id;
        fOque.value          = oque          ?? '';
        fOnde.value          = onde          ?? '';
        fQuando.value        = quando        ?? '';
        fQuem.value          = quem          ?? '';
        fQuanto.value        = quanto        ?? '';
        fComo.value          = como          ?? '';
        fJustificativa.value = justificativa ?? '';

        if (dotacoesStr && dotacoesStr.trim() !== '') {
            dotacoesStr.split(',').forEach(d => {
                adicionarDotacao(d.trim());
            });
        } else {
            adicionarDotacao();
        }
    } else {
        titulo.textContent = 'Nova iniciativa';
        fId.value            = '';
        fOque.value          = '';
        fOnde.value          = '';
        fQuando.value        = '';
        fQuem.value          = '';
        fQuanto.value        = '';
        fComo.value          = '';
        fJustificativa.value = '';
        adicionarDotacao();
    }
});

modalIniciativa.addEventListener('shown.bs.modal', event => {
    const listaDotacoes = document.getElementById('lista-dotacoes');
    if (!listaDotacoes.querySelector('.dotacao-item')) {
        adicionarDotacao();
    }
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
