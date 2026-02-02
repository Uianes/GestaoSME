<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/permissions.php';
require_login();

$conn = db();
$user = $_SESSION['user'] ?? null;
$matricula = (int)($user['matricula'] ?? 0);
$isAdmin = user_is_admin();

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

$errors = [];
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        $errors[] = 'Apenas administradores podem adicionar ou vincular alunos.';
    }
    $action = $_POST['action'] ?? '';
    if (!$hasAlunos || !$hasTurmas || !$hasTurmaAlunos) {
        $errors[] = 'As tabelas do módulo ainda não foram criadas. Execute o SQL em database/pedagogico_turmas.sql.';
    }
    if ($action === 'add_aluno' && empty($errors)) {
        $turmaNome = trim((string)($_POST['turma_nome'] ?? ''));
        $alunoNome = trim((string)($_POST['aluno_nome'] ?? ''));
        $alunoMatricula = trim((string)($_POST['aluno_matricula'] ?? ''));
        if ($turmaNome === '') {
            $errors[] = 'Informe o nome da turma.';
        }
        if ($alunoNome === '') {
            $errors[] = 'Informe o nome do aluno.';
        }
        if (empty($errors)) {
            $stmt = $conn->prepare('SELECT id FROM turmas WHERE nome = ?');
            $stmt->bind_param('s', $turmaNome);
            $stmt->execute();
            $result = $stmt->get_result();
            $turmaId = (int)($result->fetch_assoc()['id'] ?? 0);
            $stmt->close();
            if ($turmaId === 0) {
                $stmt = $conn->prepare('INSERT INTO turmas (nome) VALUES (?)');
                $stmt->bind_param('s', $turmaNome);
                $stmt->execute();
                $turmaId = $stmt->insert_id;
                $stmt->close();
            }

            $stmt = $conn->prepare('
                SELECT id FROM alunos
                WHERE nome = ? AND (matricula <=> ?)
            ');
            $stmt->bind_param('ss', $alunoNome, $alunoMatricula);
            $stmt->execute();
            $result = $stmt->get_result();
            $alunoId = (int)($result->fetch_assoc()['id'] ?? 0);
            $stmt->close();
            if ($alunoId === 0) {
                $stmt = $conn->prepare('INSERT INTO alunos (nome, matricula) VALUES (?, ?)');
                $stmt->bind_param('ss', $alunoNome, $alunoMatricula);
                $stmt->execute();
                $alunoId = $stmt->insert_id;
                $stmt->close();
            }

            $stmt = $conn->prepare('
                INSERT INTO turma_alunos (turma_id, aluno_id)
                VALUES (?, ?)
            ');
            $stmt->bind_param('ii', $turmaId, $alunoId);
            $stmt->execute();
            $stmt->close();
            header('Location: app.php?page=turmas&ok=aluno');
            exit;
        }
    }
    if ($action === 'vincular_professor' && empty($errors)) {
        if (!$hasTurmaProfessores) {
            $errors[] = 'A tabela de vínculo com professores não foi encontrada. Execute o SQL em database/pedagogico_turmas.sql.';
        } else {
            $turmaAlunoId = (int)($_POST['turma_aluno_id'] ?? 0);
            $professorMatricula = (int)($_POST['professor_matricula'] ?? 0);
            if ($turmaAlunoId <= 0) {
                $errors[] = 'Selecione um aluno da turma.';
            }
            if ($professorMatricula <= 0) {
                $errors[] = 'Selecione um professor.';
            }
            if (empty($errors)) {
                $stmt = $conn->prepare('SELECT turma_id, aluno_id FROM turma_alunos WHERE id = ?');
                $stmt->bind_param('i', $turmaAlunoId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();
                $turmaId = (int)($row['turma_id'] ?? 0);
                $alunoId = (int)($row['aluno_id'] ?? 0);
                if ($turmaId <= 0 || $alunoId <= 0) {
                    $errors[] = 'Não foi possível identificar turma e aluno para o vínculo.';
                }
            }
            if (empty($errors)) {
                $stmt = $conn->prepare('
                    INSERT INTO turma_professor_alunos (turma_id, aluno_id, professor_matricula)
                    VALUES (?, ?, ?)
                ');
                $stmt->bind_param('iii', $turmaId, $alunoId, $professorMatricula);
                $stmt->execute();
                $stmt->close();
                header('Location: app.php?page=turmas&ok=vinculo');
                exit;
            }
        }
    }
}

if (isset($_GET['ok'])) {
    if ($_GET['ok'] === 'aluno') {
        $notice = 'Aluno cadastrado com sucesso.';
    }
    if ($_GET['ok'] === 'vinculo') {
        $notice = 'Vínculo do aluno com professor salvo.';
    }
}

$alunos = [];
if ($hasAlunos && $hasTurmas && $hasTurmaAlunos) {
    if ($isAdmin) {
        $result = $conn->query('
            SELECT ta.id, t.nome AS turma_nome, a.nome AS aluno_nome, a.matricula AS aluno_matricula, ta.created_at
            FROM turma_alunos ta
            INNER JOIN turmas t ON t.id = ta.turma_id
            INNER JOIN alunos a ON a.id = ta.aluno_id
            ORDER BY t.nome, a.nome
        ');
        while ($row = $result->fetch_assoc()) {
            $alunos[] = $row;
        }
    } else {
        $stmt = $conn->prepare('
            SELECT ta.id, t.nome AS turma_nome, a.nome AS aluno_nome, a.matricula AS aluno_matricula, ta.created_at
            FROM turma_alunos ta
            INNER JOIN turmas t ON t.id = ta.turma_id
            INNER JOIN alunos a ON a.id = ta.aluno_id
            INNER JOIN turma_professor_alunos v ON v.turma_id = ta.turma_id AND v.aluno_id = ta.aluno_id
            WHERE v.professor_matricula = ?
            ORDER BY t.nome, a.nome
        ');
        $stmt->bind_param('i', $matricula);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $alunos[] = $row;
        }
        $stmt->close();
    }
}

$professores = [];
if ($isAdmin && table_exists($conn, 'usuarios')) {
    $result = $conn->query('
        SELECT matricula, nome
        FROM usuarios
        ORDER BY nome
    ');
    while ($row = $result->fetch_assoc()) {
        $professores[] = $row;
    }
}

$vinculos = [];
if ($hasAlunos && $hasTurmas && $hasTurmaAlunos && $hasTurmaProfessores) {
    if ($isAdmin) {
        $result = $conn->query('
            SELECT v.id, v.created_at, t.nome AS turma_nome, a.nome AS aluno_nome, a.matricula AS aluno_matricula, u.nome AS professor_nome, v.professor_matricula
            FROM turma_professor_alunos v
            INNER JOIN turmas t ON t.id = v.turma_id
            INNER JOIN alunos a ON a.id = v.aluno_id
            LEFT JOIN usuarios u ON u.matricula = v.professor_matricula
            ORDER BY t.nome, a.nome
        ');
        while ($row = $result->fetch_assoc()) {
            $vinculos[] = $row;
        }
    } else {
        $stmt = $conn->prepare('
            SELECT v.id, v.created_at, t.nome AS turma_nome, a.nome AS aluno_nome, a.matricula AS aluno_matricula, u.nome AS professor_nome, v.professor_matricula
            FROM turma_professor_alunos v
            INNER JOIN turmas t ON t.id = v.turma_id
            INNER JOIN alunos a ON a.id = v.aluno_id
            LEFT JOIN usuarios u ON u.matricula = v.professor_matricula
            WHERE v.professor_matricula = ?
            ORDER BY t.nome, a.nome
        ');
        $stmt->bind_param('i', $matricula);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $vinculos[] = $row;
        }
        $stmt->close();
    }
}
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h3 class="mb-1 text-body-emphasis">Minhas turmas</h3>
        <div class="text-muted">Administre alunos e vínculos com professores.</div>
    </div>
</div>

<?php if ($notice): ?>
    <div class="alert alert-success"><?= h($notice) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$hasAlunos || !$hasTurmas || !$hasTurmaAlunos || !$hasTurmaProfessores): ?>
    <div class="alert alert-warning">
        As tabelas necessárias ainda não existem. Execute o script `database/pedagogico_turmas.sql`.
    </div>
<?php endif; ?>

<?php if ($isAdmin): ?>
    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Cadastrar aluno em turma</h5>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="add_aluno">
                        <div class="col-12">
                            <label class="form-label">Turma</label>
                            <input type="text" name="turma_nome" class="form-control" placeholder="Ex: 5º Ano B" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nome do aluno</label>
                            <input type="text" name="aluno_nome" class="form-control" placeholder="Nome completo" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Matrícula do aluno (opcional)</label>
                            <input type="text" name="aluno_matricula" class="form-control" placeholder="Ex: 20250123">
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-dark" type="submit">
                                <i class="bi bi-person-plus me-2"></i>Adicionar aluno
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Vincular aluno a professor</h5>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="vincular_professor">
                        <div class="col-12">
                            <label class="form-label">Aluno da turma</label>
                            <select class="form-select" name="turma_aluno_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($alunos as $aluno): ?>
                                    <option value="<?= (int)$aluno['id'] ?>">
                                        <?= h($aluno['turma_nome']) ?> — <?= h($aluno['aluno_nome']) ?>
                                        <?php if (!empty($aluno['aluno_matricula'])): ?>
                                            (<?= h($aluno['aluno_matricula']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Professor</label>
                            <select class="form-select" name="professor_matricula" required>
                                <option value="">Selecione</option>
                                <?php foreach ($professores as $professor): ?>
                                    <option value="<?= (int)$professor['matricula'] ?>">
                                        <?= h($professor['nome']) ?> (<?= (int)$professor['matricula'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-link-45deg me-2"></i>Salvar vínculo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        Você possui acesso somente para visualizar suas turmas vinculadas.
    </div>
<?php endif; ?>

<div class="card shadow-sm mt-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Alunos cadastrados</h5>
        <?php if (empty($alunos)): ?>
            <div class="text-muted">Nenhum aluno cadastrado ainda.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Turma</th>
                            <th>Aluno</th>
                            <th>Matrícula</th>
                            <th>Cadastrado em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alunos as $aluno): ?>
                            <tr>
                                <td><?= h($aluno['turma_nome']) ?></td>
                                <td><?= h($aluno['aluno_nome']) ?></td>
                                <td><?= h($aluno['aluno_matricula'] ?: '-') ?></td>
                                <td><?= h(date('d/m/Y', strtotime($aluno['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Vínculos com professores</h5>
        <?php if (empty($vinculos)): ?>
            <div class="text-muted">Nenhum vínculo cadastrado ainda.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Turma</th>
                            <th>Aluno</th>
                            <th>Matrícula</th>
                            <th>Professor</th>
                            <th>Vinculado em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vinculos as $vinculo): ?>
                            <tr>
                                <td><?= h($vinculo['turma_nome']) ?></td>
                                <td><?= h($vinculo['aluno_nome']) ?></td>
                                <td><?= h($vinculo['aluno_matricula'] ?: '-') ?></td>
                                <td>
                                    <?= h($vinculo['professor_nome'] ?: 'Professor não encontrado') ?>
                                    (<?= (int)$vinculo['professor_matricula'] ?>)
                                </td>
                                <td><?= h(date('d/m/Y', strtotime($vinculo['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
