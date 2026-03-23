<?php
require 'conexao.php';

// RESUMO GERAL
$sqlResumo = "SELECT 
        COUNT(DISTINCT i.id_iniciativa) AS total_iniciativas,
        COUNT(ip.id_passo)              AS total_passos,
        COALESCE(SUM(ip.concluido),0)   AS passos_concluidos
    FROM iniciativa i
    LEFT JOIN iniciativa_passo ip ON ip.id_iniciativa = i.id_iniciativa";
$resResumo = $pdo->query($sqlResumo)->fetch();

$totalIniciativas   = (int) ($resResumo['total_iniciativas'] ?? 0);
$totalPassos        = (int) ($resResumo['total_passos'] ?? 0);
$passosConcluidos   = (int) ($resResumo['passos_concluidos'] ?? 0);
$percGeral          = $totalPassos > 0 ? round(($passosConcluidos / $totalPassos) * 100) : 0;

// LISTA POR INICIATIVA (agrupado com hierarquia)
$sqlLista = "
SELECT 
    o.nome_orgao,
    p.nome_programa,
    a.id_acao,
    a.nome_acao,
    m.id_meta,
    m.indicador,
    i.id_iniciativa,
    i.Oque,
    COUNT(ip.id_passo)            AS total_passos,
    COALESCE(SUM(ip.concluido),0) AS passos_concluidos
FROM orgaos o
JOIN programa p       ON p.id_orgao     = o.id_orgao
JOIN acao a           ON a.id_programa  = p.id_programa
JOIN meta m           ON m.id_acao      = a.id_acao
JOIN iniciativa i     ON i.id_meta      = m.id_meta
LEFT JOIN iniciativa_passo ip ON ip.id_iniciativa = i.id_iniciativa
GROUP BY 
    o.nome_orgao,
    p.nome_programa,
    a.id_acao,
    a.nome_acao,
    m.id_meta,
    m.indicador,
    i.id_iniciativa,
    i.Oque
ORDER BY 
    o.nome_orgao,
    p.nome_programa,
    a.id_acao,
    m.id_meta,
    i.id_iniciativa
";
$stmt = $pdo->query($sqlLista);
$linhas = $stmt->fetchAll();

$pageTitle = "Painel de execução do PPA";
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h4 mb-0">Painel de execução do PPA</h1>
    <a href="orgaos.php" class="btn btn-sm btn-outline-secondary">
        &larr; Estrutura do PPA
    </a>
</div>

<!-- Cards de resumo -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small mb-1">Iniciativas</h6>
                <p class="display-6 mb-0"><?= $totalIniciativas ?></p>
                <small class="text-muted">Cadastradas no sistema</small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small mb-1">Passos da checklist</h6>
                <p class="display-6 mb-0"><?= $totalPassos ?></p>
                <small class="text-muted">Itens de execução registrados</small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small mb-1">Execução geral</h6>
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <p class="display-6 mb-0"><?= $percGeral ?>%</p>
                    <small class="text-muted">dos passos concluídos</small>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-success" style="width: <?= $percGeral ?>%;"></div>
                </div>
                <small class="text-muted d-block mt-1">
                    <?= $passosConcluidos ?> de <?= $totalPassos ?> passos concluídos
                </small>
            </div>
        </div>
    </div>
</div>

<?php if (empty($linhas)): ?>
    <div class="alert alert-info">
        Nenhuma iniciativa com checklist cadastrada ainda.
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="card-header">
            <strong>Execução por iniciativa</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Órgão</th>
                            <th>Programa</th>
                            <th>Ação</th>
                            <th>Meta / Indicador</th>
                            <th>Iniciativa</th>
                            <th class="text-center" style="width: 150px;">Progresso</th>
                            <th class="text-end" style="width: 120px;">Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhas as $row): 
                            $tot = (int) $row['total_passos'];
                            $con = (int) $row['passos_concluidos'];
                            $perc = $tot > 0 ? round(($con / $tot) * 100) : 0;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nome_orgao']) ?></td>
                                <td><?= htmlspecialchars($row['nome_programa']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['id_acao']) ?></strong>
                                    <br>
                                    <small><?= htmlspecialchars($row['nome_acao']) ?></small>
                                </td>
                                <td>
                                    <strong>#<?= $row['id_meta'] ?></strong>
                                    <br>
                                    <small><?= nl2br(htmlspecialchars($row['indicador'])) ?></small>
                                </td>
                                <td>
                                    <small><?= nl2br(htmlspecialchars($row['Oque'])) ?></small>
                                </td>
                                <td class="text-center">
                                    <?php if ($tot > 0): ?>
                                        <div class="small mb-1">
                                            <?= $con ?> / <?= $tot ?> (<?= $perc ?>%)
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: <?= $perc ?>%;"></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Sem passos</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="iniciativas.php?id_meta=<?= $row['id_meta'] ?>#ini-<?= $row['id_iniciativa'] ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        Abrir iniciativas
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include 'layout.php';