<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';
require_login();

$conn = db();

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

$hasAlunos = table_exists($conn, 'alunos');
$hasTurmas = table_exists($conn, 'turmas');
$hasTurmaAlunos = table_exists($conn, 'turma_alunos');
$hasTurmaProfessores = table_exists($conn, 'turma_professor_alunos');

$stats = [
    'turmas' => 0,
    'alunos' => 0,
    'vinculos' => 0,
    'professores' => 0,
];

$turmasResumo = [];

if ($hasTurmas && $hasAlunos && $hasTurmaAlunos) {
    $result = $conn->query('SELECT COUNT(*) AS total FROM alunos');
    $stats['alunos'] = (int)($result->fetch_assoc()['total'] ?? 0);
    $result = $conn->query('SELECT COUNT(*) AS total FROM turmas');
    $stats['turmas'] = (int)($result->fetch_assoc()['total'] ?? 0);
    $result = $conn->query('
        SELECT t.nome AS turma_nome, COUNT(ta.aluno_id) AS total
        FROM turmas t
        LEFT JOIN turma_alunos ta ON ta.turma_id = t.id
        GROUP BY t.id, t.nome
        ORDER BY total DESC, t.nome ASC
        LIMIT 8
    ');
    while ($row = $result->fetch_assoc()) {
        $turmasResumo[] = $row;
    }
}

if ($hasTurmaProfessores) {
    $result = $conn->query('SELECT COUNT(*) AS total FROM turma_professor_alunos');
    $stats['vinculos'] = (int)($result->fetch_assoc()['total'] ?? 0);
    $result = $conn->query('SELECT COUNT(DISTINCT professor_matricula) AS total FROM turma_professor_alunos');
    $stats['professores'] = (int)($result->fetch_assoc()['total'] ?? 0);
}
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h3 class="mb-1 text-body-emphasis">Avaliações externas</h3>
        <div class="text-muted">Resultados de aprendizagem e panorama das turmas.</div>
    </div>
</div>

<?php if (!$hasAlunos || !$hasTurmas || !$hasTurmaAlunos || !$hasTurmaProfessores): ?>
    <div class="alert alert-warning">
        As tabelas do módulo de turmas ainda não existem. Execute o script `database/pedagogico_turmas.sql` para começar.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Turmas cadastradas</div>
                <div class="display-6"><?= h($stats['turmas']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Alunos cadastrados</div>
                <div class="display-6"><?= h($stats['alunos']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Vínculos aluno-professor</div>
                <div class="display-6"><?= h($stats['vinculos']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Professores vinculados</div>
                <div class="display-6"><?= h($stats['professores']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Resumo por turma</h5>
                <?php if (empty($turmasResumo)): ?>
                    <div class="text-muted">Sem dados ainda. Cadastre alunos em "Minhas turmas".</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Turma</th>
                                    <th>Total de alunos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($turmasResumo as $turma): ?>
                                    <tr>
                                        <td><?= h($turma['turma_nome']) ?></td>
                                        <td><?= h($turma['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Resultados de aprendizagem</h5>
                <div class="text-muted mb-3">
                    Este painel será alimentado nos próximos passos com indicadores, avaliações e evolução por turma.
                </div>
                <div class="border rounded-3 p-3 bg-body-tertiary">
                    <div class="small text-muted mb-1">Status</div>
                    <div class="fw-semibold">Aguardando definição dos indicadores.</div>
                </div>
            </div>
        </div>
    </div>
</div>
