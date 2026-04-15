<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/permissions.php';

require_login();

$conn = db();
$userMatricula = (int)($_SESSION['user']['matricula'] ?? 0);
$canManage = user_can_access_system('alunos_inclusao');

function h_inclusao($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function table_exists_inclusao(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

function column_exists_inclusao(mysqli $conn, string $table, string $column): bool
{
    $tableSafe = $conn->real_escape_string($table);
    $columnSafe = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
    return $result && $result->num_rows > 0;
}

function inclusao_run_query(mysqli $conn, string $sql, array $params = []): mysqli_result|bool
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

function ph_list(int $count): string
{
    return implode(',', array_fill(0, $count, '?'));
}

function normalize_sme(string $name): string
{
    return preg_replace('/[^A-Z]/', '', strtoupper($name));
}

function inclusao_tipo_profissional_label(?string $tipo): string
{
    return match ((string)$tipo) {
        'monitor' => 'Monitor',
        'interprete_libras' => 'Intérprete de LIBRAS',
        'cuidador' => 'Cuidador',
        'outro' => 'Outro',
        default => 'Monitor',
    };
}

function inclusao_join_values(array $values): string
{
    $values = array_values(array_filter(array_map(static function ($value): string {
        return trim((string)$value);
    }, $values), static fn(string $value): bool => $value !== '' && $value !== '-'));

    return $values !== [] ? implode(' / ', $values) : '-';
}

function inclusao_allowed_laudo_extensions(): array
{
    return [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];
}

function inclusao_fetch_laudo_atual(mysqli $conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $result = inclusao_run_query(
        $conn,
        'SELECT laudo_caminho, laudo_nome_original, laudo_mime FROM alunos_aee WHERE id = ? LIMIT 1',
        [$id]
    );
    if (!$result instanceof mysqli_result) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    return is_array($row) ? $row : null;
}

function inclusao_remove_laudo_path(?string $relativePath): void
{
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') {
        return;
    }
    $fullPath = realpath(__DIR__ . '/../' . $relativePath);
    $baseDir = realpath(__DIR__ . '/../uploads/alunos_aee_laudos');
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

function inclusao_store_laudo(int $registroId, array $file): array
{
    if ($registroId <= 0) {
        throw new RuntimeException('Registro inválido para upload do laudo.');
    }
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return [];
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload do laudo.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 10 * 1024 * 1024) {
        throw new RuntimeException('O laudo deve ter no máximo 10 MB.');
    }

    $originalName = basename((string)($file['name'] ?? ''));
    if ($originalName === '') {
        throw new RuntimeException('Nome do laudo inválido.');
    }

    $allowed = inclusao_allowed_laudo_extensions();
    $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '' || !isset($allowed[$extension])) {
        throw new RuntimeException('Formato de laudo inválido. Envie PDF ou imagem.');
    }

    $detectedMime = mime_content_type((string)($file['tmp_name'] ?? '')) ?: ((string)($file['type'] ?? 'application/octet-stream'));
    if (!in_array($detectedMime, $allowed[$extension], true)) {
        throw new RuntimeException('Tipo de laudo inválido.');
    }

    $folder = __DIR__ . '/../uploads/alunos_aee_laudos/' . $registroId;
    if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
        throw new RuntimeException('Não foi possível criar a pasta do laudo.');
    }

    $filename = uniqid('laudo_', true) . '.' . $extension;
    $target = $folder . '/' . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException('Não foi possível salvar o laudo.');
    }

    return [
        'laudo_caminho' => 'uploads/alunos_aee_laudos/' . $registroId . '/' . $filename,
        'laudo_nome_original' => $originalName,
        'laudo_mime' => $detectedMime,
    ];
}

function inclusao_user_context(mysqli $conn, int $matricula): array
{
    if ($matricula <= 0) {
        return [false, []];
    }
    if (
        !table_exists_inclusao($conn, 'vinculo')
        || !table_exists_inclusao($conn, 'unidade')
        || !table_exists_inclusao($conn, 'orgaos')
        || !column_exists_inclusao($conn, 'vinculo', 'matricula')
        || !column_exists_inclusao($conn, 'vinculo', 'id_unidade')
        || !column_exists_inclusao($conn, 'vinculo', 'id_orgao')
        || !column_exists_inclusao($conn, 'unidade', 'id_unidade')
        || !column_exists_inclusao($conn, 'unidade', 'nome')
        || !column_exists_inclusao($conn, 'orgaos', 'id_orgao')
        || !column_exists_inclusao($conn, 'orgaos', 'nome_orgao')
    ) {
        return [false, []];
    }
    $sql = "
        SELECT DISTINCT v.id_unidade, u.nome AS unidade_nome, o.nome_orgao
        FROM vinculo v
        LEFT JOIN unidade u ON u.id_unidade = v.id_unidade
        LEFT JOIN orgaos o ON o.id_orgao = v.id_orgao
        WHERE v.matricula = ?
        ORDER BY u.nome
    ";
    $result = inclusao_run_query($conn, $sql, [$matricula]);
    if (!$result instanceof mysqli_result) {
        return [false, []];
    }
    $isSme = false;
    $units = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $unitId = (int)($row['id_unidade'] ?? 0);
        if ($unitId > 0) {
            $units[] = $unitId;
        }
        $unitName = (string)($row['unidade_nome'] ?? '');
        $orgaoName = (string)($row['nome_orgao'] ?? '');
        if (normalize_sme($unitName) === 'SME' || normalize_sme($orgaoName) === 'SME') {
            $isSme = true;
        }
    }
    return [$isSme, array_values(array_unique($units))];
}

$hasAee = table_exists_inclusao($conn, 'alunos_aee');
$hasAlunoIdInAee = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'aluno_id');
$hasNomeMonitor = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'nome_monitor');
$hasMatriculaMonitorTexto = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'matricula_monitor');
$hasMonitorMatricula = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'monitor_matricula');
$hasAcompanhanteMatricula = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'acompanhante_matricula');
$hasAcompanhanteMatricula2 = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'acompanhante_matricula_2');
$hasTipoAcompanhamento = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'tipo_acompanhamento');
$hasTipoAcompanhamento2 = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'tipo_acompanhamento_2');
$hasParticipaAee = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'participa_aee');
$hasCidEmitidoPor = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'cid_emitido_por');
$hasCidEmitidoPorOutro = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'cid_emitido_por_outro');
$hasTerapiaPostoSaude = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'terapia_posto_saude');
$hasTerapiaParticular = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'terapia_particular');
$hasTerapiaOutras = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'terapia_outras');
$hasTerapiaNaoSei = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'terapia_nao_sei');
$hasUsaMedicacao = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'usa_medicacao');
$hasLaudoCaminho = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'laudo_caminho');
$hasLaudoNomeOriginal = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'laudo_nome_original');
$hasLaudoMime = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'laudo_mime');
$hasLaudoSchema = $hasLaudoCaminho && $hasLaudoNomeOriginal && $hasLaudoMime;
$monitorColumn = $hasNomeMonitor
    ? 'nome_monitor'
    : ($hasMatriculaMonitorTexto ? 'matricula_monitor' : ($hasMonitorMatricula ? 'monitor_matricula' : null));
$professionalMatriculaColumn = $hasAcompanhanteMatricula
    ? 'acompanhante_matricula'
    : ($hasMonitorMatricula ? 'monitor_matricula' : null);
$hasMonitorField = $monitorColumn !== null;
$schemaOk = $hasAee
    && column_exists_inclusao($conn, 'alunos_aee', 'id')
    && column_exists_inclusao($conn, 'alunos_aee', 'nome_aluno')
    && column_exists_inclusao($conn, 'alunos_aee', 'serie')
    && column_exists_inclusao($conn, 'alunos_aee', 'data_nascimento')
    && column_exists_inclusao($conn, 'alunos_aee', 'diagnostico_fechado')
    && column_exists_inclusao($conn, 'alunos_aee', 'suspeita')
    && column_exists_inclusao($conn, 'alunos_aee', 'cid')
    && column_exists_inclusao($conn, 'alunos_aee', 'descricao_outro')
    && column_exists_inclusao($conn, 'alunos_aee', 'frequenta_teabraca')
    && column_exists_inclusao($conn, 'alunos_aee', 'monitor_exclusivo')
    && column_exists_inclusao($conn, 'alunos_aee', 'matricula_usuario');
$hasAlunos = table_exists_inclusao($conn, 'alunos');
$hasAlunoId = $hasAlunos && column_exists_inclusao($conn, 'alunos', 'id');
$hasAlunoNome = $hasAlunos && column_exists_inclusao($conn, 'alunos', 'nome');
$hasAlunoMatricula = $hasAlunos && column_exists_inclusao($conn, 'alunos', 'matricula');
$hasUsuarios = table_exists_inclusao($conn, 'usuarios');
$hasTurmas = table_exists_inclusao($conn, 'turmas');
$hasTurmaAlunos = table_exists_inclusao($conn, 'turma_alunos');
$hasVinculo = table_exists_inclusao($conn, 'vinculo');
$hasUnidade = table_exists_inclusao($conn, 'unidade');
$hasUsuariosAtivo = $hasUsuarios && column_exists_inclusao($conn, 'usuarios', 'ativo');
$hasTurmasIdEscola = $hasTurmas && column_exists_inclusao($conn, 'turmas', 'id_escola');
$hasTurmaAlunosTurmaId = $hasTurmaAlunos && column_exists_inclusao($conn, 'turma_alunos', 'turma_id');
$hasTurmaAlunosAlunoId = $hasTurmaAlunos && column_exists_inclusao($conn, 'turma_alunos', 'aluno_id');
$hasVinculoIdUnidade = $hasVinculo && column_exists_inclusao($conn, 'vinculo', 'id_unidade');

$canScopeBySchool = $hasTurmasIdEscola && $hasTurmaAlunosTurmaId && $hasTurmaAlunosAlunoId;
$canScopeUsersByUnit = $hasVinculo && $hasVinculoIdUnidade;

[$userIsSme, $userUnits] = inclusao_user_context($conn, $userMatricula);
$filterAluno = trim((string)($_GET['f_aluno'] ?? ''));
$filterCid = trim((string)($_GET['f_cid'] ?? ''));

$errors = [];
$notice = null;
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
$modalOpen = false;
$modalMode = 'create';
$formData = [];
$allowedCidEmitidoPor = ['medico', 'psicologo', 'outro', 'nao_sei'];
$allowedUsaMedicacao = ['sim', 'nao', 'nao_sei'];
$allowedTiposProfissional = ['monitor', 'interprete_libras', 'cuidador', 'outro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasAee && $schemaOk) {
    if (!$canManage) {
        $errors[] = 'Você não tem permissão para alterar dados.';
    } else {
        $action = (string)($_POST['action'] ?? 'save');
        $id = (int)($_POST['id'] ?? 0);
        $alunoIdRaw = trim((string)($_POST['aluno_id'] ?? ''));
        $serie = trim((string)($_POST['serie'] ?? ''));
        $dataNascimento = trim((string)($_POST['data_nascimento'] ?? ''));
        $diagnosticoFechado = isset($_POST['diagnostico_fechado']) ? 1 : 0;
        $suspeita = isset($_POST['suspeita']) ? 1 : 0;
        $cid = trim((string)($_POST['cid'] ?? ''));
        $descricaoOutro = trim((string)($_POST['descricao_outro'] ?? ''));
        $frequentaTeabraca = isset($_POST['frequenta_teabraca']) ? 1 : 0;
        $participaAee = isset($_POST['participa_aee']) ? 1 : 0;
        $monitorExclusivo = isset($_POST['monitor_exclusivo']) ? 1 : 0;
        $monitorRef = trim((string)($_POST['monitor_ref'] ?? ''));
        $monitorRef2 = trim((string)($_POST['monitor_ref_2'] ?? ''));
        $tipoProfissional = trim((string)($_POST['tipo_profissional'] ?? 'monitor'));
        $tipoProfissional2 = trim((string)($_POST['tipo_profissional_2'] ?? 'monitor'));
        $cidEmitidoPor = trim((string)($_POST['cid_emitido_por'] ?? ''));
        $cidEmitidoPorOutro = trim((string)($_POST['cid_emitido_por_outro'] ?? ''));
        $terapiaPostoSaude = isset($_POST['terapia_posto_saude']) ? 1 : 0;
        $terapiaParticular = isset($_POST['terapia_particular']) ? 1 : 0;
        $terapiaOutras = isset($_POST['terapia_outras']) ? 1 : 0;
        $terapiaNaoSei = isset($_POST['terapia_nao_sei']) ? 1 : 0;
        $usaMedicacao = trim((string)($_POST['usa_medicacao'] ?? ''));
        $removeLaudo = isset($_POST['remove_laudo']) ? 1 : 0;
        $monitorDbValue = null;
        $monitorDbValue2 = null;
        $modalMode = $id > 0 ? 'edit' : 'create';
        $modalOpen = $action === 'save';
        $formData = $_POST;

        if ($monitorRef !== '' || $monitorRef2 !== '') {
            $monitorExclusivo = 1;
            $formData['monitor_exclusivo'] = '1';
        }

        if ($action === 'delete') {
            if ($id <= 0) {
                $errors[] = 'Registro inválido para exclusão.';
            } else {
                if ($hasLaudoSchema) {
                    $laudoAtual = inclusao_fetch_laudo_atual($conn, $id);
                    inclusao_remove_laudo_path((string)($laudoAtual['laudo_caminho'] ?? ''));
                }
                inclusao_run_query($conn, 'DELETE FROM alunos_aee WHERE id = ?', [$id]);
                $notice = 'Registro excluído com sucesso.';
            }
            $modalOpen = false;
        }

        if ($action !== 'save') {
            goto inclusao_post_end;
        }

        if ($hasMonitorField && $monitorExclusivo === 1 && $monitorRef !== '') {
            if ($professionalMatriculaColumn !== null) {
                $monitorDbValue = ctype_digit($monitorRef) ? (int)$monitorRef : null;
            } else {
                $monitorDbValue = $monitorRef;
            }
        }
        if ($professionalMatriculaColumn !== null && $monitorExclusivo === 1 && $monitorRef2 !== '') {
            $monitorDbValue2 = ctype_digit($monitorRef2) ? (int)$monitorRef2 : null;
        }

        if ($alunoIdRaw === '') {
            $errors[] = 'Selecione o aluno.';
        }
        if ($hasMonitorField && $monitorExclusivo === 1 && $monitorRef === '' && $monitorRef2 === '') {
            $errors[] = 'Selecione ao menos um profissional de apoio.';
        }
        if ($hasMonitorField && $monitorExclusivo === 1 && $professionalMatriculaColumn !== null && $monitorRef !== '' && $monitorDbValue === null) {
            $errors[] = 'Monitor inválido.';
        }
        if ($professionalMatriculaColumn !== null && $monitorExclusivo === 1 && $monitorRef2 !== '' && $monitorDbValue2 === null) {
            $errors[] = 'Segundo profissional inválido.';
        }
        if ($monitorExclusivo === 1 && !in_array($tipoProfissional, $allowedTiposProfissional, true)) {
            $errors[] = 'Selecione um tipo de profissional válido.';
        }
        if ($monitorExclusivo === 1 && $monitorRef2 !== '' && !in_array($tipoProfissional2, $allowedTiposProfissional, true)) {
            $errors[] = 'Selecione um tipo válido para o segundo profissional.';
        }
        if (
            $monitorExclusivo === 1
            && $professionalMatriculaColumn !== null
            && $monitorDbValue !== null
            && $monitorDbValue2 !== null
            && $monitorDbValue === $monitorDbValue2
        ) {
            $errors[] = 'Selecione profissionais diferentes para os dois apoios.';
        }
        if ($cidEmitidoPor !== '' && !in_array($cidEmitidoPor, $allowedCidEmitidoPor, true)) {
            $errors[] = 'Seleção inválida em emissor do CID.';
        }
        if ($cidEmitidoPor === 'outro' && $cidEmitidoPorOutro === '') {
            $errors[] = 'Informe qual outro profissional emitiu o CID.';
        }
        if ($usaMedicacao !== '' && !in_array($usaMedicacao, $allowedUsaMedicacao, true)) {
            $errors[] = 'Seleção inválida em uso de medicação.';
        }

        if (empty($errors)) {
            try {
            if ($hasAlunoId) {
                $resAlunoInfo = mysqli_execute_query($conn, "SELECT id, nome, matricula FROM alunos WHERE id = ? LIMIT 1", [(int)$alunoIdRaw]);
            } elseif ($hasAlunoMatricula) {
                $resAlunoInfo = mysqli_execute_query($conn, "SELECT NULL AS id, nome, matricula FROM alunos WHERE matricula = ? LIMIT 1", [$alunoIdRaw]);
            } else {
                $resAlunoInfo = false;
            }
            $rowAlunoInfo = $resAlunoInfo ? mysqli_fetch_assoc($resAlunoInfo) : null;
            $nomeAluno = (string)($rowAlunoInfo['nome'] ?? '');
            $matriculaAlunoRaw = (string)($rowAlunoInfo['matricula'] ?? '');
            $matriculaAluno = ctype_digit($matriculaAlunoRaw) ? (int)$matriculaAlunoRaw : null;

            if ($nomeAluno === '') {
                $errors[] = 'Aluno não encontrado na tabela alunos.';
            } else {
                $dataSql = null;
                if ($dataNascimento !== '') {
                    $ts = strtotime(str_replace('/', '-', $dataNascimento));
                    $dataSql = $ts ? date('Y-m-d', $ts) : null;
                }

                $fields = [];
                if ($hasAlunoIdInAee) {
                    $fields['aluno_id'] = $hasAlunoId ? (int)$alunoIdRaw : (ctype_digit($alunoIdRaw) ? (int)$alunoIdRaw : null);
                }
                $fields['nome_aluno'] = $nomeAluno;
                $fields['serie'] = $serie !== '' ? $serie : null;
                $fields['data_nascimento'] = $dataSql;
                $fields['diagnostico_fechado'] = $diagnosticoFechado;
                $fields['suspeita'] = $suspeita;
                $fields['cid'] = $cid !== '' ? $cid : null;
                $fields['descricao_outro'] = $descricaoOutro !== '' ? $descricaoOutro : null;
                $fields['frequenta_teabraca'] = $frequentaTeabraca;
                if ($hasParticipaAee) {
                    $fields['participa_aee'] = $participaAee;
                }
                $fields['monitor_exclusivo'] = $monitorExclusivo;
                if ($professionalMatriculaColumn !== null) {
                    $fields[$professionalMatriculaColumn] = $monitorExclusivo === 1 ? $monitorDbValue : null;
                }
                if ($hasTipoAcompanhamento) {
                    $fields['tipo_acompanhamento'] = $monitorExclusivo === 1 ? $tipoProfissional : null;
                }
                if ($hasAcompanhanteMatricula2) {
                    $fields['acompanhante_matricula_2'] = $monitorExclusivo === 1 ? $monitorDbValue2 : null;
                }
                if ($hasTipoAcompanhamento2) {
                    $fields['tipo_acompanhamento_2'] = $monitorExclusivo === 1 && $monitorDbValue2 !== null ? $tipoProfissional2 : null;
                }
                if ($hasMonitorField) {
                    if ($professionalMatriculaColumn === null) {
                        $fields[$monitorColumn] = $monitorExclusivo === 1 ? $monitorDbValue : null;
                    } elseif ($monitorColumn === 'nome_monitor') {
                        $monitorNome = null;
                        foreach ($monitoresSelect as $monitorOption) {
                            if ((string)(int)($monitorOption['matricula'] ?? 0) === (string)$monitorDbValue) {
                                $monitorNome = (string)($monitorOption['nome'] ?? '');
                                break;
                            }
                        }
                        $fields[$monitorColumn] = $monitorExclusivo === 1 && $monitorNome !== '' ? $monitorNome : null;
                    } elseif ($monitorColumn === 'matricula_monitor') {
                        $fields[$monitorColumn] = $monitorExclusivo === 1 && $monitorDbValue !== null ? (string)$monitorDbValue : null;
                    } elseif ($monitorColumn === 'monitor_matricula' && $professionalMatriculaColumn !== 'monitor_matricula') {
                        $fields[$monitorColumn] = $monitorExclusivo === 1 ? $monitorDbValue : null;
                    }
                }
                $fields['matricula_usuario'] = $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null;
                if ($hasCidEmitidoPor) {
                    $fields['cid_emitido_por'] = $cidEmitidoPor !== '' ? $cidEmitidoPor : null;
                }
                if ($hasCidEmitidoPorOutro) {
                    $fields['cid_emitido_por_outro'] = $cidEmitidoPor === 'outro' && $cidEmitidoPorOutro !== '' ? $cidEmitidoPorOutro : null;
                }
                if ($hasTerapiaPostoSaude) {
                    $fields['terapia_posto_saude'] = $terapiaPostoSaude;
                }
                if ($hasTerapiaParticular) {
                    $fields['terapia_particular'] = $terapiaParticular;
                }
                if ($hasTerapiaOutras) {
                    $fields['terapia_outras'] = $terapiaOutras;
                }
                if ($hasTerapiaNaoSei) {
                    $fields['terapia_nao_sei'] = $terapiaNaoSei;
                }
                if ($hasUsaMedicacao) {
                    $fields['usa_medicacao'] = $usaMedicacao !== '' ? $usaMedicacao : null;
                }

                $registroId = $id;
                if ($id > 0) {
                    $setParts = [];
                    $params = [];
                    foreach ($fields as $column => $value) {
                        $setParts[] = "{$column} = ?";
                        $params[] = $value;
                    }
                    $params[] = $id;
                    $sqlUpdate = "UPDATE alunos_aee SET " . implode(', ', $setParts) . " WHERE id = ?";
                    $updated = mysqli_execute_query($conn, $sqlUpdate, $params);
                    if ($updated === false) {
                        throw new RuntimeException('Falha ao atualizar o registro: ' . $conn->error);
                    }
                    $notice = 'Registro atualizado com sucesso.';
                } else {
                    $columns = array_keys($fields);
                    $params = array_values($fields);
                    $sqlInsert = "INSERT INTO alunos_aee (" . implode(', ', $columns) . ") VALUES (" . ph_list(count($columns)) . ")";
                    $inserted = mysqli_execute_query($conn, $sqlInsert, $params);
                    if ($inserted === false) {
                        throw new RuntimeException('Falha ao criar o registro: ' . $conn->error);
                    }
                    $registroId = (int)$conn->insert_id;
                    $notice = 'Registro criado com sucesso.';
                }

                $laudoAtual = $hasLaudoSchema ? inclusao_fetch_laudo_atual($conn, $registroId) : null;
                if ($hasLaudoSchema && $removeLaudo === 1 && empty($_FILES['laudo_arquivo']['name'] ?? '')) {
                    inclusao_remove_laudo_path((string)($laudoAtual['laudo_caminho'] ?? ''));
                    $removedLaudo = inclusao_run_query(
                        $conn,
                        'UPDATE alunos_aee SET laudo_caminho = NULL, laudo_nome_original = NULL, laudo_mime = NULL WHERE id = ?',
                        [$registroId]
                    );
                    if ($removedLaudo === false) {
                        throw new RuntimeException('Falha ao remover o laudo atual: ' . $conn->error);
                    }
                }
                if ($hasLaudoSchema && isset($_FILES['laudo_arquivo'])) {
                    $laudoData = inclusao_store_laudo($registroId, $_FILES['laudo_arquivo']);
                    if ($laudoData !== []) {
                        inclusao_remove_laudo_path((string)($laudoAtual['laudo_caminho'] ?? ''));
                        $updatedLaudo = inclusao_run_query(
                            $conn,
                            'UPDATE alunos_aee SET laudo_caminho = ?, laudo_nome_original = ?, laudo_mime = ? WHERE id = ?',
                            [
                                $laudoData['laudo_caminho'],
                                $laudoData['laudo_nome_original'],
                                $laudoData['laudo_mime'],
                                $registroId,
                            ]
                        );
                        if ($updatedLaudo === false) {
                            throw new RuntimeException('Falha ao salvar o laudo no registro: ' . $conn->error);
                        }
                    }
                }
                $modalOpen = false;
            }
            } catch (RuntimeException $e) {
                $notice = null;
                $errors[] = $e->getMessage();
                $modalOpen = true;
            }
        }
    }
}
inclusao_post_end:

$alunosSelect = [];
if ($canManage && $hasAlunos && $hasAlunoNome && $hasAlunoMatricula) {
    $selectAlunoIdent = $hasAlunoId ? 'MIN(a.id)' : 'MIN(CAST(a.matricula AS UNSIGNED))';
    $sqlTodosAlunos = "
        SELECT {$selectAlunoIdent} AS id, MAX(a.nome) AS nome, a.matricula
        FROM alunos a
        GROUP BY a.matricula
        ORDER BY nome
    ";
    $resAlunos = inclusao_run_query($conn, $sqlTodosAlunos);
    if ($resAlunos instanceof mysqli_result) {
        $alunosMap = [];
        while ($r = mysqli_fetch_assoc($resAlunos)) {
            $key = (string)($r['matricula'] ?? '');
            if ($key === '') {
                $key = 'id:' . (string)($r['id'] ?? '');
            }
            if (!isset($alunosMap[$key])) {
                $alunosMap[$key] = $r;
            }
        }
        $alunosSelect = array_values($alunosMap);
    }
}

$monitoresSelect = [];
if ($canManage && $hasUsuarios && $hasMonitorField) {
    if ($userIsSme) {
        $sqlMonitores = $hasUsuariosAtivo
            ? "SELECT matricula, nome FROM usuarios WHERE ativo = 1 ORDER BY nome"
            : "SELECT matricula, nome FROM usuarios ORDER BY nome";
        $resMonitores = inclusao_run_query($conn, $sqlMonitores);
    } elseif (!empty($userUnits) && $canScopeUsersByUnit) {
        $sqlMonitores = "
            SELECT u.matricula, u.nome
            FROM usuarios u
            INNER JOIN vinculo v ON v.matricula = u.matricula
            WHERE " . ($hasUsuariosAtivo ? "u.ativo = 1 AND " : "") . "v.id_unidade IN (" . ph_list(count($userUnits)) . ")
            GROUP BY u.matricula, u.nome
            ORDER BY u.nome
        ";
        $resMonitores = inclusao_run_query($conn, $sqlMonitores, $userUnits);
    } else {
        $resMonitores = false;
    }
    if ($resMonitores instanceof mysqli_result) {
        while ($r = mysqli_fetch_assoc($resMonitores)) {
            $monitoresSelect[] = $r;
        }
    }
}

$rows = [];
if ($hasAee && $schemaOk) {
    $joinAlunos = ($hasAlunoIdInAee && $hasAlunoId)
        ? "LEFT JOIN alunos a ON a.id = aa.aluno_id"
        : "LEFT JOIN alunos a ON a.matricula = aa.matricula_usuario";

    $selectTipoProfissional = $hasTipoAcompanhamento
        ? ", aa.tipo_acompanhamento AS tipo_profissional"
        : ", NULL AS tipo_profissional";
    $selectTipoProfissional2 = $hasTipoAcompanhamento2
        ? ", aa.tipo_acompanhamento_2 AS tipo_profissional_2"
        : ", NULL AS tipo_profissional_2";

    if ($professionalMatriculaColumn !== null) {
        $selectMonitor = ", aa.{$professionalMatriculaColumn} AS monitor_matricula, um.nome AS monitor_nome";
        if ($hasAcompanhanteMatricula2) {
            $selectMonitor .= ", aa.acompanhante_matricula_2 AS monitor_matricula_2, um2.nome AS monitor_nome_2";
        } else {
            $selectMonitor .= ", NULL AS monitor_matricula_2, NULL AS monitor_nome_2";
        }
        $joinMonitor = "LEFT JOIN usuarios um ON um.matricula = aa.{$professionalMatriculaColumn}";
        if ($hasAcompanhanteMatricula2) {
            $joinMonitor .= "\n                LEFT JOIN usuarios um2 ON um2.matricula = aa.acompanhante_matricula_2";
        }
    } elseif ($hasMonitorField) {
        $selectMonitor = ", aa.`{$monitorColumn}` AS monitor_nome";
        $joinMonitor = "";
    } else {
        $selectMonitor = ", NULL AS monitor_nome, NULL AS monitor_matricula_2, NULL AS monitor_nome_2";
        $joinMonitor = "";
    }

    if ($userIsSme) {
        if ($hasUnidade && $canScopeBySchool) {
            $sqlList = "
                SELECT aa.*, COALESCE(a.matricula, aa.matricula_usuario) AS matricula_aluno, a.nome AS aluno_nome_base
                       {$selectMonitor}
                       {$selectTipoProfissional}
                       {$selectTipoProfissional2},
                       GROUP_CONCAT(DISTINCT un.nome ORDER BY un.nome SEPARATOR ', ') AS escola_nome
                FROM alunos_aee aa
                {$joinAlunos}
                {$joinMonitor}
                LEFT JOIN turma_alunos ta ON ta.aluno_id = COALESCE(a.matricula, aa.matricula_usuario)
                LEFT JOIN turmas t ON t.id = ta.turma_id
                LEFT JOIN unidade un ON un.id_unidade = t.id_escola
                GROUP BY aa.id
                ORDER BY aa.nome_aluno ASC
            ";
        } else {
            $sqlList = "
                SELECT aa.*, COALESCE(a.matricula, aa.matricula_usuario) AS matricula_aluno, a.nome AS aluno_nome_base
                       {$selectMonitor}
                       {$selectTipoProfissional}
                       {$selectTipoProfissional2},
                       NULL AS escola_nome
                FROM alunos_aee aa
                {$joinAlunos}
                {$joinMonitor}
                ORDER BY aa.nome_aluno ASC
            ";
        }
        $resRows = inclusao_run_query($conn, $sqlList);
    } elseif (!empty($userUnits) && $hasUnidade && $canScopeBySchool) {
        $sqlList = "
            SELECT aa.*, COALESCE(a.matricula, aa.matricula_usuario) AS matricula_aluno, a.nome AS aluno_nome_base
                   {$selectMonitor}
                   {$selectTipoProfissional}
                   {$selectTipoProfissional2},
                   GROUP_CONCAT(DISTINCT un.nome ORDER BY un.nome SEPARATOR ', ') AS escola_nome
            FROM alunos_aee aa
            {$joinAlunos}
            {$joinMonitor}
            INNER JOIN turma_alunos ta ON ta.aluno_id = COALESCE(a.matricula, aa.matricula_usuario)
            INNER JOIN turmas t ON t.id = ta.turma_id
            LEFT JOIN unidade un ON un.id_unidade = t.id_escola
            WHERE t.id_escola IN (" . ph_list(count($userUnits)) . ")
            GROUP BY aa.id
            ORDER BY aa.nome_aluno ASC
        ";
        $resRows = inclusao_run_query($conn, $sqlList, $userUnits);
    } else {
        $resRows = false;
    }
    if ($resRows instanceof mysqli_result) {
        while ($r = mysqli_fetch_assoc($resRows)) {
            $rows[] = $r;
        }
    }
}

if ($editId > 0) {
    foreach ($rows as $row) {
        if ((int)$row['id'] === $editId) {
            $editRow = $row;
            break;
        }
    }
}

if (!$formData && $editRow) {
    $formData = [
        'id' => (string)($editRow['id'] ?? ''),
        'aluno_id' => (string)($hasAlunoIdInAee ? ($editRow['aluno_id'] ?? '') : ($editRow['matricula_usuario'] ?? '')),
        'cid' => (string)($editRow['cid'] ?? ''),
        'descricao_outro' => (string)($editRow['descricao_outro'] ?? ''),
        'diagnostico_fechado' => (int)($editRow['diagnostico_fechado'] ?? 0) === 1 ? '1' : '',
        'suspeita' => (int)($editRow['suspeita'] ?? 0) === 1 ? '1' : '',
        'frequenta_teabraca' => (int)($editRow['frequenta_teabraca'] ?? 0) === 1 ? '1' : '',
        'participa_aee' => (int)($editRow['participa_aee'] ?? 0) === 1 ? '1' : '',
        'monitor_exclusivo' => (int)($editRow['monitor_exclusivo'] ?? 0) === 1 ? '1' : '',
        'monitor_ref' => (string)(
            $professionalMatriculaColumn !== null
                ? ($editRow[$professionalMatriculaColumn] ?? '')
                : (
            $monitorColumn === 'monitor_matricula'
                ? ($editRow['monitor_matricula'] ?? '')
                : ($editRow[$monitorColumn] ?? ''))
        ),
        'monitor_ref_2' => (string)($editRow['monitor_matricula_2'] ?? ($editRow['acompanhante_matricula_2'] ?? '')),
        'tipo_profissional' => (string)($editRow['tipo_profissional'] ?? ($editRow['tipo_acompanhamento'] ?? 'monitor')),
        'tipo_profissional_2' => (string)($editRow['tipo_profissional_2'] ?? ($editRow['tipo_acompanhamento_2'] ?? 'monitor')),
        'cid_emitido_por' => (string)($editRow['cid_emitido_por'] ?? ''),
        'cid_emitido_por_outro' => (string)($editRow['cid_emitido_por_outro'] ?? ''),
        'terapia_posto_saude' => (int)($editRow['terapia_posto_saude'] ?? 0) === 1 ? '1' : '',
        'terapia_particular' => (int)($editRow['terapia_particular'] ?? 0) === 1 ? '1' : '',
        'terapia_outras' => (int)($editRow['terapia_outras'] ?? 0) === 1 ? '1' : '',
        'terapia_nao_sei' => (int)($editRow['terapia_nao_sei'] ?? 0) === 1 ? '1' : '',
        'usa_medicacao' => (string)($editRow['usa_medicacao'] ?? ''),
        'laudo_nome_original' => (string)($editRow['laudo_nome_original'] ?? ''),
        'remove_laudo' => '',
    ];
}

if ($editRow && empty($errors)) {
    $modalMode = 'edit';
    $modalOpen = true;
}

if (!empty($rows) && ($filterAluno !== '' || $filterCid !== '')) {
    $rows = array_values(array_filter($rows, static function (array $row) use ($filterAluno, $filterCid): bool {
        $alunoNome = (string)($row['aluno_nome_base'] ?? $row['nome_aluno'] ?? '');
        $matricula = (string)($row['matricula_aluno'] ?? $row['matricula_usuario'] ?? '');
        $cid = (string)($row['cid'] ?? '');
        if ($filterAluno !== '') {
            $needle = strtolower($filterAluno);
            if (stripos(strtolower($alunoNome), $needle) === false && stripos(strtolower($matricula), $needle) === false) {
                return false;
            }
        }
        if ($filterCid !== '' && stripos(strtolower($cid), strtolower($filterCid)) === false) {
            return false;
        }
        return true;
    }));
}

$pdfParams = [];
if ($filterAluno !== '') {
    $pdfParams['aluno'] = $filterAluno;
}
if ($filterCid !== '') {
    $pdfParams['cid'] = $filterCid;
}
$pdfEscolaQuery = http_build_query(array_merge(['type' => 'escola'], $pdfParams));
$pdfGeralQuery = http_build_query(array_merge(['type' => 'geral'], $pdfParams));
$pdfCidLikeQuery = http_build_query(array_merge(['type' => 'cid_like'], $pdfParams));
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h3 class="mb-1 text-body-emphasis">Alunos de Inclusão / AEE</h3>
    </div>
    <?php if ($hasAee && $schemaOk): ?>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary btn-sm" target="_blank" href="actions/alunos_inclusao_pdf.php?<?= h_inclusao($pdfEscolaQuery) ?>">PDF da escola</a>
            <a class="btn btn-outline-secondary btn-sm" target="_blank" href="actions/alunos_inclusao_pdf.php?<?= h_inclusao($pdfCidLikeQuery) ?>">PDF por CID similar</a>
            <?php if ($userIsSme): ?>
                <a class="btn btn-primary btn-sm" target="_blank" href="actions/alunos_inclusao_pdf.php?<?= h_inclusao($pdfGeralQuery) ?>">PDF SME por escola</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($hasAee && $schemaOk): ?>
    <form method="get" action="app.php" class="row g-2 mb-3">
        <input type="hidden" name="page" value="alunos_inclusao">
        <div class="col-12 col-md-4">
            <input type="text" class="form-control form-control-sm" name="f_aluno" placeholder="Buscar aluno ou matrícula" value="<?= h_inclusao($filterAluno) ?>">
        </div>
        <div class="col-12 col-md-3">
            <input type="text" class="form-control form-control-sm" name="f_cid" placeholder="Filtrar CID (texto)" value="<?= h_inclusao($filterCid) ?>">
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
            <a class="btn btn-sm btn-outline-secondary" href="app.php?page=alunos_inclusao">Limpar</a>
        </div>
    </form>
<?php endif; ?>

<?php if (!$hasAee): ?>
    <div class="alert alert-warning">Tabela <code>alunos_aee</code> não encontrada. Execute <code>database/inclusao.sql</code>.</div>
<?php elseif (!$schemaOk): ?>
    <div class="alert alert-warning">Schema de <code>alunos_aee</code> incompatível com esta tela. Verifique as colunas obrigatórias no arquivo <code>database/inclusao.sql</code>.</div>
<?php else: ?>
    <?php if (!$hasMonitorField): ?>
        <div class="alert alert-info">
            Para vincular monitor ao aluno, adicione a coluna:
            <code>ALTER TABLE alunos_aee ADD COLUMN nome_monitor VARCHAR(130) NULL;</code>
        </div>
    <?php endif; ?>
    <?php if (!$hasParticipaAee): ?>
        <div class="alert alert-info">
            Para marcar participação no Atendimento Educacional Especializado, adicione a coluna:
            <code>ALTER TABLE alunos_aee ADD COLUMN participa_aee TINYINT(1) NOT NULL DEFAULT 0;</code>
        </div>
    <?php endif; ?>
    <?php if (!$hasTipoAcompanhamento || !$hasAcompanhanteMatricula): ?>
        <div class="alert alert-info">
            Para classificar o tipo do profissional e vinculá-lo a um usuário, adicione as colunas:
            <code>ALTER TABLE alunos_aee
                ADD COLUMN tipo_acompanhamento VARCHAR(30) NULL,
                ADD COLUMN acompanhante_matricula INT(11) NULL;</code>
        </div>
    <?php endif; ?>
    <?php if (!$hasTipoAcompanhamento2 || !$hasAcompanhanteMatricula2): ?>
        <div class="alert alert-info">
            Para cadastrar um segundo profissional de apoio no mesmo aluno, adicione as colunas:
            <code>ALTER TABLE alunos_aee
                ADD COLUMN tipo_acompanhamento_2 VARCHAR(30) NULL,
                ADD COLUMN acompanhante_matricula_2 INT(11) NULL;</code>
        </div>
    <?php endif; ?>
    <?php if (!$hasLaudoSchema): ?>
        <div class="alert alert-info">
            Para anexar o laudo do aluno, adicione as colunas:
            <code>ALTER TABLE alunos_aee
                ADD COLUMN laudo_caminho VARCHAR(255) NULL,
                ADD COLUMN laudo_nome_original VARCHAR(255) NULL,
                ADD COLUMN laudo_mime VARCHAR(100) NULL;</code>
        </div>
    <?php endif; ?>
    <?php if (!$hasCidEmitidoPor || !$hasCidEmitidoPorOutro || !$hasTerapiaPostoSaude || !$hasTerapiaParticular || !$hasTerapiaOutras || !$hasTerapiaNaoSei || !$hasUsaMedicacao): ?>
        <div class="alert alert-info">
            Para habilitar os novos campos do modal, adicione as colunas:
            <code>ALTER TABLE alunos_aee
                ADD COLUMN cid_emitido_por VARCHAR(30) NULL,
                ADD COLUMN cid_emitido_por_outro VARCHAR(130) NULL,
                ADD COLUMN terapia_posto_saude TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN terapia_particular TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN terapia_outras TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN terapia_nao_sei TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN usa_medicacao VARCHAR(20) NULL;</code>
        </div>
    <?php endif; ?>
    <?php if ($notice): ?><div class="alert alert-success"><?= h_inclusao($notice) ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h_inclusao($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width:44px;height:44px" data-aee-open-create title="Novo registro">
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h5 class="card-title mb-0">Registros AEE</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-group-clear>
                    Limpar agrupamento
                </button>
            </div>
            <?php if (empty($rows)): ?>
                <p class="text-muted mb-0">Nenhum registro encontrado para sua escola.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" data-aee-table>
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Matrícula</th>
                                <th>Escola</th>
                                <th>CID</th>
                                <th>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" data-group-by="diagnostico_fechado">
                                        Diag.
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" data-group-by="suspeita">
                                        Suspeita
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" data-group-by="frequenta_teabraca">
                                        TEAbraça
                                    </button>
                                </th>
                                <?php if ($hasParticipaAee): ?>
                                    <th>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold text-start" data-group-by="participa_aee">
                                            Atendimento AEE
                                        </button>
                                    </th>
                                <?php endif; ?>
                                <th>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" data-group-by="monitor_exclusivo">
                                        Apoio
                                    </button>
                                </th>
                                <th>Tipo</th>
                                <?php if ($hasMonitorField): ?><th>Profissionais</th><?php endif; ?>
                                <?php if ($hasLaudoSchema): ?><th>Laudo</th><?php endif; ?>
                                <?php if ($canManage): ?><th>Ações</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody data-aee-tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr
                                    data-diagnostico_fechado="<?= (int)$row['diagnostico_fechado'] === 1 ? 'sim' : 'nao' ?>"
                                    data-suspeita="<?= (int)$row['suspeita'] === 1 ? 'sim' : 'nao' ?>"
                                    data-frequenta_teabraca="<?= (int)$row['frequenta_teabraca'] === 1 ? 'sim' : 'nao' ?>"
                                    data-monitor_exclusivo="<?= (int)$row['monitor_exclusivo'] === 1 ? 'sim' : 'nao' ?>"
                                    <?php if ($hasParticipaAee): ?>
                                        data-participa_aee="<?= (int)($row['participa_aee'] ?? 0) === 1 ? 'sim' : 'nao' ?>"
                                    <?php endif; ?>
                                >
                                    <td class="fw-semibold"><?= h_inclusao($row['aluno_nome_base'] ?? $row['nome_aluno']) ?></td>
                                    <td><?= h_inclusao($row['matricula_aluno'] ?? $row['matricula_usuario'] ?? '-') ?></td>
                                    <td><?= h_inclusao($row['escola_nome'] ?? '-') ?></td>
                                    <td><?= h_inclusao($row['cid'] ?: '-') ?></td>
                                    <td><?= (int)$row['diagnostico_fechado'] === 1 ? 'Sim' : 'Não' ?></td>
                                    <td><?= (int)$row['suspeita'] === 1 ? 'Sim' : 'Não' ?></td>
                                    <td><?= (int)$row['frequenta_teabraca'] === 1 ? 'Sim' : 'Não' ?></td>
                                    <?php if ($hasParticipaAee): ?>
                                        <td><?= (int)($row['participa_aee'] ?? 0) === 1 ? 'Sim' : 'Não' ?></td>
                                    <?php endif; ?>
                                    <td><?= (int)$row['monitor_exclusivo'] === 1 ? 'Sim' : 'Não' ?></td>
                                    <td><?=
                                        (int)$row['monitor_exclusivo'] === 1
                                            ? h_inclusao(inclusao_join_values([
                                                !empty($row['monitor_nome'] ?? '')
                                                    ? inclusao_tipo_profissional_label($row['tipo_profissional'] ?? 'monitor')
                                                    : '',
                                                !empty($row['monitor_nome_2'] ?? '') || !empty($row['monitor_matricula_2'] ?? '')
                                                    ? inclusao_tipo_profissional_label($row['tipo_profissional_2'] ?? 'monitor')
                                                    : '',
                                            ]))
                                            : '-'
                                    ?></td>
                                    <?php if ($hasMonitorField): ?>
                                        <td><?= h_inclusao(inclusao_join_values([
                                            ($row['monitor_nome'] ?? '') !== '' ? $row['monitor_nome'] : '',
                                            ($row['monitor_nome_2'] ?? '') !== '' ? $row['monitor_nome_2'] : '',
                                        ])) ?></td>
                                    <?php endif; ?>
                                    <?php if ($hasLaudoSchema): ?>
                                        <td>
                                            <?php if (!empty($row['laudo_caminho'])): ?>
                                                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="actions/alunos_inclusao_laudo.php?id=<?= (int)$row['id'] ?>">Visualizar</a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <?php if ($canManage): ?>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-aee-open-edit
                                                    data-id="<?= (int)$row['id'] ?>"
                                                    data-aluno-id="<?= h_inclusao((string)($hasAlunoIdInAee ? ($row['aluno_id'] ?? '') : ($row['matricula_usuario'] ?? ''))) ?>"
                                                    data-cid="<?= h_inclusao((string)($row['cid'] ?? '')) ?>"
                                                    data-descricao="<?= h_inclusao((string)($row['descricao_outro'] ?? '')) ?>"
                                                    data-diagnostico="<?= (int)($row['diagnostico_fechado'] ?? 0) === 1 ? '1' : '0' ?>"
                                                    data-suspeita="<?= (int)($row['suspeita'] ?? 0) === 1 ? '1' : '0' ?>"
                                                    data-teabraca="<?= (int)($row['frequenta_teabraca'] ?? 0) === 1 ? '1' : '0' ?>"
                                                    data-participa-aee="<?= (int)($row['participa_aee'] ?? 0) === 1 ? '1' : '0' ?>"
                                                    data-monitor-exclusivo="<?= (int)($row['monitor_exclusivo'] ?? 0) === 1 ? '1' : '0' ?>"
                                                    data-monitor-ref="<?= h_inclusao((string)($hasMonitorField ? ($professionalMatriculaColumn !== null ? ($row[$professionalMatriculaColumn] ?? '') : ($row[$monitorColumn] ?? '')) : '')) ?>"
                                                    data-monitor-ref-2="<?= h_inclusao((string)($row['monitor_matricula_2'] ?? '')) ?>"
                                                    data-tipo-profissional="<?= h_inclusao((string)($row['tipo_profissional'] ?? 'monitor')) ?>"
                                                    data-tipo-profissional-2="<?= h_inclusao((string)($row['tipo_profissional_2'] ?? 'monitor')) ?>"
                                                    data-cid-emitido-por="<?= h_inclusao((string)($row['cid_emitido_por'] ?? '')) ?>"
                                                    data-cid-emitido-por-outro="<?= h_inclusao((string)($row['cid_emitido_por_outro'] ?? '')) ?>"
                                                    data-terapia-posto-saude="<?= (int)($row['terapia_posto_saude'] ?? 0) === 1 ? '1' : '0' ?>"
                                                    data-terapia-particular="<?= (int)($row['terapia_particular'] ?? 0) === 1 ? '1' : '0' ?>"
                                                    data-terapia-outras="<?= (int)($row['terapia_outras'] ?? 0) === 1 ? '1' : '0' ?>"
                                                    data-terapia-nao-sei="<?= (int)($row['terapia_nao_sei'] ?? 0) === 1 ? '1' : '0' ?>"
                                                    data-usa-medicacao="<?= h_inclusao((string)($row['usa_medicacao'] ?? '')) ?>"
                                                >
                                                    Editar
                                                </button>
                                                <form method="post" onsubmit="return confirm('Excluir este registro?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($canManage && $hasAee && $schemaOk): ?>
    <div class="modal fade" id="aeeModal" tabindex="-1" aria-labelledby="aeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form method="post" id="aeeForm" class="modal-content" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="aeeModalLabel"><?= $modalMode === 'edit' ? 'Editar registro AEE' : 'Novo registro AEE' ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="aee-id" value="<?= h_inclusao((string)($formData['id'] ?? '')) ?>">
                        <input type="hidden" name="serie" value="<?= h_inclusao((string)($formData['serie'] ?? '')) ?>">
                        <input type="hidden" name="data_nascimento" value="<?= h_inclusao((string)($formData['data_nascimento'] ?? '')) ?>">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="aee-aluno">Aluno</label>
                                <select class="form-select" name="aluno_id" id="aee-aluno" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($alunosSelect as $a): ?>
                                        <?php $alunoOptionValue = (string)($hasAlunoId ? ($a['id'] ?? '') : ($a['matricula'] ?? '')); ?>
                                        <option value="<?= h_inclusao($alunoOptionValue) ?>" <?= ((string)($formData['aluno_id'] ?? '') === $alunoOptionValue) ? 'selected' : '' ?>>
                                            <?= h_inclusao($a['nome']) ?> (<?= h_inclusao($a['matricula'] ?? '-') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12"><hr class="my-1"></div>
                            <div class="col-12">
                                <label class="form-label" for="aee-cid">CID</label>
                                <input type="text" class="form-control" name="cid" id="aee-cid" value="<?= h_inclusao((string)($formData['cid'] ?? '')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="aee-descricao">Descrição</label>
                                <input type="text" class="form-control" name="descricao_outro" id="aee-descricao" value="<?= h_inclusao((string)($formData['descricao_outro'] ?? '')) ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <input class="form-check-input" type="checkbox" name="diagnostico_fechado" id="aee-diagnostico" <?= !empty($formData['diagnostico_fechado']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="aee-diagnostico">Diagnóstico fechado</label>
                            </div>
                            <div class="col-12 col-md-6 form-check">
                                <input class="form-check-input" type="checkbox" name="suspeita" id="aee-suspeita" <?= !empty($formData['suspeita']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="aee-suspeita">Suspeita</label>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="aee-cid-emitido-por">CID emitido por</label>
                                <select class="form-select" name="cid_emitido_por" id="aee-cid-emitido-por">
                                    <option value="">Selecione</option>
                                    <option value="medico" <?= (($formData['cid_emitido_por'] ?? '') === 'medico') ? 'selected' : '' ?>>Médico</option>
                                    <option value="psicologo" <?= (($formData['cid_emitido_por'] ?? '') === 'psicologo') ? 'selected' : '' ?>>Psicólogo</option>
                                    <option value="outro" <?= (($formData['cid_emitido_por'] ?? '') === 'outro') ? 'selected' : '' ?>>Outro profissional</option>
                                    <option value="nao_sei" <?= (($formData['cid_emitido_por'] ?? '') === 'nao_sei') ? 'selected' : '' ?>>Não sei informar</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="aee-cid-emitido-por-outro">Qual outro profissional</label>
                                <input type="text" class="form-control" name="cid_emitido_por_outro" id="aee-cid-emitido-por-outro" value="<?= h_inclusao((string)($formData['cid_emitido_por_outro'] ?? '')) ?>">
                            </div>
                            <div class="col-12"><hr class="my-1"></div>
                            <?php if ($hasParticipaAee): ?>
                                <div class="col-12 form-check">
                                    <input class="form-check-input" type="checkbox" name="participa_aee" id="aee-participa-aee" <?= !empty($formData['participa_aee']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aee-participa-aee">Participa do Atendimento Educacional AEE?</label>
                                </div>
                            <?php endif; ?>
                            <div class="col-12"><hr class="my-1"></div>
                            <div class="col-12 form-check">
                                <input class="form-check-input" type="checkbox" name="monitor_exclusivo" id="aee-monitor-exclusivo" <?= !empty($formData['monitor_exclusivo']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="aee-monitor-exclusivo">Tem monitor exclusivo?</label>
                            </div>
                            <?php if ($hasMonitorField): ?>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="aee-monitor-ref">Profissional de apoio 1</label>
                                    <select class="form-select" name="monitor_ref" id="aee-monitor-ref">
                                        <option value="">Selecione</option>
                                        <?php foreach ($monitoresSelect as $m): ?>
                                            <?php $valorMonitor = $professionalMatriculaColumn !== null ? (string)(int)$m['matricula'] : (string)$m['nome']; ?>
                                            <option value="<?= h_inclusao($valorMonitor) ?>" <?= ((string)($formData['monitor_ref'] ?? '') === $valorMonitor) ? 'selected' : '' ?>>
                                                <?= h_inclusao($m['nome']) ?> (<?= (int)$m['matricula'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="aee-tipo-profissional">Tipo do apoio 1</label>
                                    <select class="form-select" name="tipo_profissional" id="aee-tipo-profissional">
                                        <option value="monitor" <?= (($formData['tipo_profissional'] ?? 'monitor') === 'monitor') ? 'selected' : '' ?>>Monitor</option>
                                        <option value="interprete_libras" <?= (($formData['tipo_profissional'] ?? '') === 'interprete_libras') ? 'selected' : '' ?>>Intérprete de LIBRAS</option>
                                        <option value="cuidador" <?= (($formData['tipo_profissional'] ?? '') === 'cuidador') ? 'selected' : '' ?>>Cuidador</option>
                                        <option value="outro" <?= (($formData['tipo_profissional'] ?? '') === 'outro') ? 'selected' : '' ?>>Outro</option>
                                    </select>
                                </div>
                                <?php if ($hasAcompanhanteMatricula2): ?>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="aee-monitor-ref-2">Profissional de apoio 2</label>
                                        <select class="form-select" name="monitor_ref_2" id="aee-monitor-ref-2">
                                            <option value="">Selecione</option>
                                            <?php foreach ($monitoresSelect as $m): ?>
                                                <?php $valorMonitor2 = (string)(int)$m['matricula']; ?>
                                                <option value="<?= h_inclusao($valorMonitor2) ?>" <?= ((string)($formData['monitor_ref_2'] ?? '') === $valorMonitor2) ? 'selected' : '' ?>>
                                                    <?= h_inclusao($m['nome']) ?> (<?= (int)$m['matricula'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hasTipoAcompanhamento2): ?>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="aee-tipo-profissional-2">Tipo do apoio 2</label>
                                        <select class="form-select" name="tipo_profissional_2" id="aee-tipo-profissional-2">
                                            <option value="monitor" <?= (($formData['tipo_profissional_2'] ?? 'monitor') === 'monitor') ? 'selected' : '' ?>>Monitor</option>
                                            <option value="interprete_libras" <?= (($formData['tipo_profissional_2'] ?? '') === 'interprete_libras') ? 'selected' : '' ?>>Intérprete de LIBRAS</option>
                                            <option value="cuidador" <?= (($formData['tipo_profissional_2'] ?? '') === 'cuidador') ? 'selected' : '' ?>>Cuidador</option>
                                            <option value="outro" <?= (($formData['tipo_profissional_2'] ?? '') === 'outro') ? 'selected' : '' ?>>Outro</option>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($hasLaudoSchema): ?>
                                <div class="col-12"><hr class="my-1"></div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="aee-laudo-arquivo">Laudo do aluno</label>
                                    <input type="file" class="form-control" name="laudo_arquivo" id="aee-laudo-arquivo" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                    <div class="form-text">Opcional. Formatos aceitos: PDF, JPG, PNG e WEBP. Máximo de 10 MB.</div>
                                </div>
                                <div class="col-12 col-md-6 d-flex align-items-end">
                                    <?php if (!empty($formData['laudo_nome_original'] ?? '')): ?>
                                        <div class="small text-muted">Arquivo atual: <?= h_inclusao((string)$formData['laudo_nome_original']) ?></div>
                                    <?php elseif (!empty($editRow['laudo_nome_original'] ?? '')): ?>
                                        <div class="small text-muted">
                                            Arquivo atual:
                                            <a target="_blank" href="actions/alunos_inclusao_laudo.php?id=<?= (int)$editRow['id'] ?>"><?= h_inclusao((string)$editRow['laudo_nome_original']) ?></a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($formData['laudo_nome_original'] ?? '') || !empty($editRow['laudo_nome_original'] ?? '')): ?>
                                    <div class="col-12 form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_laudo" id="aee-remove-laudo" <?= !empty($formData['remove_laudo']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="aee-remove-laudo">Remover laudo atual</label>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="col-12"><hr class="my-1"></div>
                            <div class="col-12">
                                <label class="form-label d-block">Quais terapias realiza?</label>
                                <div class="row g-2">
                                    <div class="col-12 col-md-6 form-check">
                                        <input class="form-check-input" type="checkbox" name="terapia_posto_saude" id="aee-terapia-posto-saude" <?= !empty($formData['terapia_posto_saude']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="aee-terapia-posto-saude">Acompanhamento no Posto de Saúde</label>
                                    </div>
                                    <div class="col-12 col-md-6 form-check">
                                        <input class="form-check-input" type="checkbox" name="terapia_particular" id="aee-terapia-particular" <?= !empty($formData['terapia_particular']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="aee-terapia-particular">Acompanhamento particular</label>
                                    </div>
                                    <div class="col-12 col-md-6 form-check">
                                        <input class="form-check-input" type="checkbox" name="frequenta_teabraca" id="aee-teabraca" <?= !empty($formData['frequenta_teabraca']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="aee-teabraca">TEAbraça</label>
                                    </div>
                                    <div class="col-12 col-md-6 form-check">
                                        <input class="form-check-input" type="checkbox" name="terapia_outras" id="aee-terapia-outras" <?= !empty($formData['terapia_outras']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="aee-terapia-outras">Outras</label>
                                    </div>
                                    <div class="col-12 col-md-6 form-check">
                                        <input class="form-check-input" type="checkbox" name="terapia_nao_sei" id="aee-terapia-nao-sei" <?= !empty($formData['terapia_nao_sei']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="aee-terapia-nao-sei">Não sei informar</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12"><hr class="my-1"></div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="aee-usa-medicacao">Usa medicação?</label>
                                <select class="form-select" name="usa_medicacao" id="aee-usa-medicacao">
                                    <option value="">Selecione</option>
                                    <option value="sim" <?= (($formData['usa_medicacao'] ?? '') === 'sim') ? 'selected' : '' ?>>Sim</option>
                                    <option value="nao" <?= (($formData['usa_medicacao'] ?? '') === 'nao') ? 'selected' : '' ?>>Não</option>
                                    <option value="nao_sei" <?= (($formData['usa_medicacao'] ?? '') === 'nao_sei') ? 'selected' : '' ?>>Não sei informar</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="aee-submit-button"><?= $modalMode === 'edit' ? 'Salvar alterações' : 'Criar registro' ?></button>
                    </div>
                </form>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('[data-aee-table]');
    const tbody = document.querySelector('[data-aee-tbody]');
    const clearButton = document.querySelector('[data-group-clear]');

    if (table && tbody) {
        const originalRows = Array.from(tbody.querySelectorAll('tr'));
        const groupButtons = Array.from(document.querySelectorAll('[data-group-by]'));
        let activeGroup = null;

        function updateButtons() {
            groupButtons.forEach(function (button) {
                const isActive = button.dataset.groupBy === activeGroup;
                button.classList.toggle('text-primary', isActive);
                button.classList.toggle('text-body', !isActive);
            });
            if (clearButton) {
                clearButton.classList.toggle('d-none', activeGroup === null);
            }
        }

        function appendRows(rows) {
            rows.forEach(function (row) {
                tbody.appendChild(row);
            });
        }

        function createGroupRow(label, count, colSpan) {
            const tr = document.createElement('tr');
            tr.className = 'table-light';
            tr.setAttribute('data-group-header', 'true');

            const td = document.createElement('td');
            td.colSpan = colSpan;
            td.className = 'fw-semibold text-body-emphasis';
            td.textContent = label + ' (' + count + ')';

            tr.appendChild(td);
            return tr;
        }

        function resetGrouping() {
            activeGroup = null;
            tbody.querySelectorAll('[data-group-header]').forEach(function (row) {
                row.remove();
            });
            appendRows(originalRows);
            updateButtons();
        }

        function applyGrouping(key) {
            activeGroup = key;
            tbody.querySelectorAll('[data-group-header]').forEach(function (row) {
                row.remove();
            });

            const yesRows = [];
            const noRows = [];

            originalRows.forEach(function (row) {
                if (row.dataset[key] === 'sim') {
                    yesRows.push(row);
                } else {
                    noRows.push(row);
                }
            });

            const colSpan = table.tHead.rows[0].cells.length;
            tbody.appendChild(createGroupRow('Sim', yesRows.length, colSpan));
            appendRows(yesRows);
            tbody.appendChild(createGroupRow('Não', noRows.length, colSpan));
            appendRows(noRows);
            updateButtons();
        }

        groupButtons.forEach(function (button) {
            button.classList.add('text-body');
            button.addEventListener('click', function () {
                const key = button.dataset.groupBy;
                if (activeGroup === key) {
                    resetGrouping();
                    return;
                }
                applyGrouping(key);
            });
        });

        if (clearButton) {
            clearButton.addEventListener('click', resetGrouping);
        }
    }

    const aeeModalEl = document.getElementById('aeeModal');
    if (!aeeModalEl || typeof bootstrap === 'undefined') {
        return;
    }

    const aeeModal = new bootstrap.Modal(aeeModalEl);
    const aeeForm = document.getElementById('aeeForm');
    const titleEl = document.getElementById('aeeModalLabel');
    const submitButton = document.getElementById('aee-submit-button');
    const formDefaults = {
        id: '',
        aluno_id: '',
        cid: '',
        descricao_outro: '',
        diagnostico_fechado: false,
        suspeita: false,
        frequenta_teabraca: false,
        participa_aee: false,
        monitor_exclusivo: false,
        monitor_ref: '',
        monitor_ref_2: '',
        tipo_profissional: 'monitor',
        tipo_profissional_2: 'monitor',
        cid_emitido_por: '',
        cid_emitido_por_outro: '',
        terapia_posto_saude: false,
        terapia_particular: false,
        terapia_outras: false,
        terapia_nao_sei: false,
        remove_laudo: false,
        usa_medicacao: ''
    };

    function setValue(id, value) {
        const field = document.getElementById(id);
        if (field) field.value = value;
    }

    function setChecked(id, checked) {
        const field = document.getElementById(id);
        if (field) field.checked = !!checked;
    }

    function fillModal(data, mode) {
        const state = Object.assign({}, formDefaults, data || {});
        titleEl.textContent = mode === 'edit' ? 'Editar registro AEE' : 'Novo registro AEE';
        submitButton.textContent = mode === 'edit' ? 'Salvar alterações' : 'Criar registro';
        setValue('aee-id', state.id || '');
        setValue('aee-aluno', state.aluno_id || '');
        setValue('aee-cid', state.cid || '');
        setValue('aee-descricao', state.descricao_outro || '');
        setValue('aee-monitor-ref', state.monitor_ref || '');
        setValue('aee-monitor-ref-2', state.monitor_ref_2 || '');
        setValue('aee-tipo-profissional', state.tipo_profissional || 'monitor');
        setValue('aee-tipo-profissional-2', state.tipo_profissional_2 || 'monitor');
        setValue('aee-cid-emitido-por', state.cid_emitido_por || '');
        setValue('aee-cid-emitido-por-outro', state.cid_emitido_por_outro || '');
        setValue('aee-usa-medicacao', state.usa_medicacao || '');
        setChecked('aee-diagnostico', state.diagnostico_fechado);
        setChecked('aee-suspeita', state.suspeita);
        setChecked('aee-teabraca', state.frequenta_teabraca);
        setChecked('aee-participa-aee', state.participa_aee);
        setChecked('aee-monitor-exclusivo', state.monitor_exclusivo);
        setChecked('aee-terapia-posto-saude', state.terapia_posto_saude);
        setChecked('aee-terapia-particular', state.terapia_particular);
        setChecked('aee-terapia-outras', state.terapia_outras);
        setChecked('aee-terapia-nao-sei', state.terapia_nao_sei);
        setChecked('aee-remove-laudo', state.remove_laudo);
    }

    function syncMonitorExclusivo() {
        const field = document.getElementById('aee-monitor-exclusivo');
        const monitor1 = document.getElementById('aee-monitor-ref');
        const monitor2 = document.getElementById('aee-monitor-ref-2');
        if (!field) {
            return;
        }
        const hasMonitor1 = !!(monitor1 && monitor1.value);
        const hasMonitor2 = !!(monitor2 && monitor2.value);
        if (hasMonitor1 || hasMonitor2) {
            field.checked = true;
        }
    }

    const createButton = document.querySelector('[data-aee-open-create]');
    if (createButton) {
        createButton.addEventListener('click', function () {
            fillModal({}, 'create');
            syncMonitorExclusivo();
            aeeModal.show();
        });
    }

    ['aee-monitor-ref', 'aee-monitor-ref-2'].forEach(function (id) {
        const field = document.getElementById(id);
        if (!field) {
            return;
        }
        field.addEventListener('change', syncMonitorExclusivo);
    });

    document.querySelectorAll('[data-aee-open-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            fillModal({
                id: button.dataset.id || '',
                aluno_id: button.dataset.alunoId || '',
                cid: button.dataset.cid || '',
                descricao_outro: button.dataset.descricao || '',
                diagnostico_fechado: button.dataset.diagnostico === '1',
                suspeita: button.dataset.suspeita === '1',
                frequenta_teabraca: button.dataset.teabraca === '1',
                participa_aee: button.dataset.participaAee === '1',
                monitor_exclusivo: button.dataset.monitorExclusivo === '1',
                monitor_ref: button.dataset.monitorRef || '',
                monitor_ref_2: button.dataset.monitorRef2 || '',
                tipo_profissional: button.dataset.tipoProfissional || 'monitor',
                tipo_profissional_2: button.dataset.tipoProfissional2 || 'monitor',
                cid_emitido_por: button.dataset.cidEmitidoPor || '',
                cid_emitido_por_outro: button.dataset.cidEmitidoPorOutro || '',
                terapia_posto_saude: button.dataset.terapiaPostoSaude === '1',
                terapia_particular: button.dataset.terapiaParticular === '1',
                terapia_outras: button.dataset.terapiaOutras === '1',
                terapia_nao_sei: button.dataset.terapiaNaoSei === '1',
                remove_laudo: false,
                usa_medicacao: button.dataset.usaMedicacao || ''
            }, 'edit');
            syncMonitorExclusivo();
            aeeModal.show();
        });
    });

    <?php if ($modalOpen): ?>
        fillModal({
            id: <?= json_encode((string)($formData['id'] ?? '')) ?>,
            aluno_id: <?= json_encode((string)($formData['aluno_id'] ?? '')) ?>,
            cid: <?= json_encode((string)($formData['cid'] ?? '')) ?>,
            descricao_outro: <?= json_encode((string)($formData['descricao_outro'] ?? '')) ?>,
            diagnostico_fechado: <?= !empty($formData['diagnostico_fechado']) ? 'true' : 'false' ?>,
            suspeita: <?= !empty($formData['suspeita']) ? 'true' : 'false' ?>,
            frequenta_teabraca: <?= !empty($formData['frequenta_teabraca']) ? 'true' : 'false' ?>,
            participa_aee: <?= !empty($formData['participa_aee']) ? 'true' : 'false' ?>,
            monitor_exclusivo: <?= !empty($formData['monitor_exclusivo']) ? 'true' : 'false' ?>,
            monitor_ref: <?= json_encode((string)($formData['monitor_ref'] ?? '')) ?>,
            monitor_ref_2: <?= json_encode((string)($formData['monitor_ref_2'] ?? '')) ?>,
            tipo_profissional: <?= json_encode((string)($formData['tipo_profissional'] ?? 'monitor')) ?>,
            tipo_profissional_2: <?= json_encode((string)($formData['tipo_profissional_2'] ?? 'monitor')) ?>,
            cid_emitido_por: <?= json_encode((string)($formData['cid_emitido_por'] ?? '')) ?>,
            cid_emitido_por_outro: <?= json_encode((string)($formData['cid_emitido_por_outro'] ?? '')) ?>,
            terapia_posto_saude: <?= !empty($formData['terapia_posto_saude']) ? 'true' : 'false' ?>,
            terapia_particular: <?= !empty($formData['terapia_particular']) ? 'true' : 'false' ?>,
            terapia_outras: <?= !empty($formData['terapia_outras']) ? 'true' : 'false' ?>,
            terapia_nao_sei: <?= !empty($formData['terapia_nao_sei']) ? 'true' : 'false' ?>,
            remove_laudo: <?= !empty($formData['remove_laudo']) ? 'true' : 'false' ?>,
            usa_medicacao: <?= json_encode((string)($formData['usa_medicacao'] ?? '')) ?>
        }, <?= json_encode($modalMode) ?>);
        syncMonitorExclusivo();
        aeeModal.show();
    <?php endif; ?>
});
</script>
