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

function ph_list(int $count): string
{
    return implode(',', array_fill(0, $count, '?'));
}

function normalize_sme(string $name): string
{
    return preg_replace('/[^A-Z]/', '', strtoupper($name));
}

function inclusao_user_context(mysqli $conn, int $matricula): array
{
    if ($matricula <= 0) {
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
    $result = mysqli_execute_query($conn, $sql, [$matricula]);
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
$hasParticipaAee = $hasAee && column_exists_inclusao($conn, 'alunos_aee', 'participa_aee');
$monitorColumn = $hasNomeMonitor
    ? 'nome_monitor'
    : ($hasMatriculaMonitorTexto ? 'matricula_monitor' : ($hasMonitorMatricula ? 'monitor_matricula' : null));
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
$hasUsuarios = table_exists_inclusao($conn, 'usuarios');
$hasTurmas = table_exists_inclusao($conn, 'turmas');
$hasTurmaAlunos = table_exists_inclusao($conn, 'turma_alunos');

[$userIsSme, $userUnits] = inclusao_user_context($conn, $userMatricula);
$filterAluno = trim((string)($_GET['f_aluno'] ?? ''));
$filterCid = trim((string)($_GET['f_cid'] ?? ''));

$errors = [];
$notice = null;
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasAee && $schemaOk) {
    if (!$canManage) {
        $errors[] = 'Você não tem permissão para alterar dados.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $alunoId = (int)($_POST['aluno_id'] ?? 0);
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
        $monitorDbValue = null;
        if ($hasMonitorField && $monitorExclusivo === 1 && $monitorRef !== '') {
            if ($monitorColumn === 'monitor_matricula') {
                $monitorDbValue = ctype_digit($monitorRef) ? (int)$monitorRef : null;
            } else {
                $monitorDbValue = $monitorRef;
            }
        }

        if ($alunoId <= 0) {
            $errors[] = 'Selecione o aluno.';
        }
        if ($hasMonitorField && $monitorExclusivo === 1 && $monitorRef === '') {
            $errors[] = 'Selecione o usuário monitor.';
        }
        if ($hasMonitorField && $monitorExclusivo === 1 && $monitorColumn === 'monitor_matricula' && $monitorDbValue === null) {
            $errors[] = 'Monitor inválido.';
        }

        if (empty($errors)) {
            $resAlunoInfo = mysqli_execute_query($conn, "SELECT id, nome, matricula FROM alunos WHERE id = ? LIMIT 1", [$alunoId]);
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

                if ($id > 0) {
                    if ($hasAlunoIdInAee) {
                        if ($hasMonitorField) {
                            if ($hasParticipaAee) {
                                $sqlUpdate = "
                                    UPDATE alunos_aee
                                    SET aluno_id = ?, nome_aluno = ?, serie = ?, data_nascimento = ?, diagnostico_fechado = ?, suspeita = ?,
                                        cid = ?, descricao_outro = ?, frequenta_teabraca = ?, participa_aee = ?, monitor_exclusivo = ?, {$monitorColumn} = ?, matricula_usuario = ?
                                    WHERE id = ?
                                ";
                                mysqli_execute_query($conn, $sqlUpdate, [
                                    $alunoId, $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $participaAee, $monitorExclusivo, $monitorExclusivo === 1 ? $monitorDbValue : null,
                                    $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null, $id
                                ]);
                            } else {
                                $sqlUpdate = "
                                    UPDATE alunos_aee
                                    SET aluno_id = ?, nome_aluno = ?, serie = ?, data_nascimento = ?, diagnostico_fechado = ?, suspeita = ?,
                                        cid = ?, descricao_outro = ?, frequenta_teabraca = ?, monitor_exclusivo = ?, {$monitorColumn} = ?, matricula_usuario = ?
                                    WHERE id = ?
                                ";
                                mysqli_execute_query($conn, $sqlUpdate, [
                                    $alunoId, $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $monitorExclusivo, $monitorExclusivo === 1 ? $monitorDbValue : null,
                                    $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null, $id
                                ]);
                            }
                        } else {
                            if ($hasParticipaAee) {
                                $sqlUpdate = "
                                    UPDATE alunos_aee
                                    SET aluno_id = ?, nome_aluno = ?, serie = ?, data_nascimento = ?, diagnostico_fechado = ?, suspeita = ?,
                                        cid = ?, descricao_outro = ?, frequenta_teabraca = ?, participa_aee = ?, monitor_exclusivo = ?, matricula_usuario = ?
                                    WHERE id = ?
                                ";
                                mysqli_execute_query($conn, $sqlUpdate, [
                                    $alunoId, $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $participaAee, $monitorExclusivo, $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null, $id
                                ]);
                            } else {
                                $sqlUpdate = "
                                    UPDATE alunos_aee
                                    SET aluno_id = ?, nome_aluno = ?, serie = ?, data_nascimento = ?, diagnostico_fechado = ?, suspeita = ?,
                                        cid = ?, descricao_outro = ?, frequenta_teabraca = ?, monitor_exclusivo = ?, matricula_usuario = ?
                                    WHERE id = ?
                                ";
                                mysqli_execute_query($conn, $sqlUpdate, [
                                    $alunoId, $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $monitorExclusivo, $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null, $id
                                ]);
                            }
                        }
                    } else {
                        if ($hasMonitorField) {
                            if ($hasParticipaAee) {
                                $sqlUpdate = "
                                    UPDATE alunos_aee
                                    SET nome_aluno = ?, serie = ?, data_nascimento = ?, diagnostico_fechado = ?, suspeita = ?,
                                        cid = ?, descricao_outro = ?, frequenta_teabraca = ?, participa_aee = ?, monitor_exclusivo = ?, {$monitorColumn} = ?, matricula_usuario = ?
                                    WHERE id = ?
                                ";
                                mysqli_execute_query($conn, $sqlUpdate, [
                                    $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $participaAee, $monitorExclusivo, $monitorExclusivo === 1 ? $monitorDbValue : null,
                                    $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null, $id
                                ]);
                            } else {
                                $sqlUpdate = "
                                    UPDATE alunos_aee
                                    SET nome_aluno = ?, serie = ?, data_nascimento = ?, diagnostico_fechado = ?, suspeita = ?,
                                        cid = ?, descricao_outro = ?, frequenta_teabraca = ?, monitor_exclusivo = ?, {$monitorColumn} = ?, matricula_usuario = ?
                                    WHERE id = ?
                                ";
                                mysqli_execute_query($conn, $sqlUpdate, [
                                    $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $monitorExclusivo, $monitorExclusivo === 1 ? $monitorDbValue : null,
                                    $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null, $id
                                ]);
                            }
                        } else {
                            if ($hasParticipaAee) {
                                $sqlUpdate = "
                                    UPDATE alunos_aee
                                    SET nome_aluno = ?, serie = ?, data_nascimento = ?, diagnostico_fechado = ?, suspeita = ?,
                                        cid = ?, descricao_outro = ?, frequenta_teabraca = ?, participa_aee = ?, monitor_exclusivo = ?, matricula_usuario = ?
                                    WHERE id = ?
                                ";
                                mysqli_execute_query($conn, $sqlUpdate, [
                                    $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $participaAee, $monitorExclusivo, $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null, $id
                                ]);
                            } else {
                                $sqlUpdate = "
                                    UPDATE alunos_aee
                                    SET nome_aluno = ?, serie = ?, data_nascimento = ?, diagnostico_fechado = ?, suspeita = ?,
                                        cid = ?, descricao_outro = ?, frequenta_teabraca = ?, monitor_exclusivo = ?, matricula_usuario = ?
                                    WHERE id = ?
                                ";
                                mysqli_execute_query($conn, $sqlUpdate, [
                                    $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $monitorExclusivo, $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null, $id
                                ]);
                            }
                        }
                    }
                    $notice = 'Registro atualizado com sucesso.';
                } else {
                    if ($hasAlunoIdInAee) {
                        if ($hasMonitorField) {
                            if ($hasParticipaAee) {
                                $sqlInsert = "
                                    INSERT INTO alunos_aee (
                                        aluno_id, nome_aluno, serie, data_nascimento, diagnostico_fechado, suspeita, cid,
                                        descricao_outro, frequenta_teabraca, participa_aee, monitor_exclusivo, {$monitorColumn}, matricula_usuario
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ";
                                mysqli_execute_query($conn, $sqlInsert, [
                                    $alunoId, $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $participaAee, $monitorExclusivo, $monitorExclusivo === 1 ? $monitorDbValue : null,
                                    $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null
                                ]);
                            } else {
                                $sqlInsert = "
                                    INSERT INTO alunos_aee (
                                        aluno_id, nome_aluno, serie, data_nascimento, diagnostico_fechado, suspeita, cid,
                                        descricao_outro, frequenta_teabraca, monitor_exclusivo, {$monitorColumn}, matricula_usuario
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ";
                                mysqli_execute_query($conn, $sqlInsert, [
                                    $alunoId, $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $monitorExclusivo, $monitorExclusivo === 1 ? $monitorDbValue : null,
                                    $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null
                                ]);
                            }
                        } else {
                            if ($hasParticipaAee) {
                                $sqlInsert = "
                                    INSERT INTO alunos_aee (
                                        aluno_id, nome_aluno, serie, data_nascimento, diagnostico_fechado, suspeita, cid,
                                        descricao_outro, frequenta_teabraca, participa_aee, monitor_exclusivo, matricula_usuario
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ";
                                mysqli_execute_query($conn, $sqlInsert, [
                                    $alunoId, $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $participaAee, $monitorExclusivo, $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null
                                ]);
                            } else {
                                $sqlInsert = "
                                    INSERT INTO alunos_aee (
                                        aluno_id, nome_aluno, serie, data_nascimento, diagnostico_fechado, suspeita, cid,
                                        descricao_outro, frequenta_teabraca, monitor_exclusivo, matricula_usuario
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ";
                                mysqli_execute_query($conn, $sqlInsert, [
                                    $alunoId, $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $monitorExclusivo, $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null
                                ]);
                            }
                        }
                    } else {
                        if ($hasMonitorField) {
                            if ($hasParticipaAee) {
                                $sqlInsert = "
                                    INSERT INTO alunos_aee (
                                        nome_aluno, serie, data_nascimento, diagnostico_fechado, suspeita, cid,
                                        descricao_outro, frequenta_teabraca, participa_aee, monitor_exclusivo, {$monitorColumn}, matricula_usuario
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ";
                                mysqli_execute_query($conn, $sqlInsert, [
                                    $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $participaAee, $monitorExclusivo, $monitorExclusivo === 1 ? $monitorDbValue : null,
                                    $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null
                                ]);
                            } else {
                                $sqlInsert = "
                                    INSERT INTO alunos_aee (
                                        nome_aluno, serie, data_nascimento, diagnostico_fechado, suspeita, cid,
                                        descricao_outro, frequenta_teabraca, monitor_exclusivo, {$monitorColumn}, matricula_usuario
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ";
                                mysqli_execute_query($conn, $sqlInsert, [
                                    $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $monitorExclusivo, $monitorExclusivo === 1 ? $monitorDbValue : null,
                                    $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null
                                ]);
                            }
                        } else {
                            if ($hasParticipaAee) {
                                $sqlInsert = "
                                    INSERT INTO alunos_aee (
                                        nome_aluno, serie, data_nascimento, diagnostico_fechado, suspeita, cid,
                                        descricao_outro, frequenta_teabraca, participa_aee, monitor_exclusivo, matricula_usuario
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ";
                                mysqli_execute_query($conn, $sqlInsert, [
                                    $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $participaAee, $monitorExclusivo, $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null
                                ]);
                            } else {
                                $sqlInsert = "
                                    INSERT INTO alunos_aee (
                                        nome_aluno, serie, data_nascimento, diagnostico_fechado, suspeita, cid,
                                        descricao_outro, frequenta_teabraca, monitor_exclusivo, matricula_usuario
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ";
                                mysqli_execute_query($conn, $sqlInsert, [
                                    $nomeAluno, $serie !== '' ? $serie : null, $dataSql, $diagnosticoFechado, $suspeita,
                                    $cid !== '' ? $cid : null, $descricaoOutro !== '' ? $descricaoOutro : null,
                                    $frequentaTeabraca, $monitorExclusivo, $matriculaAlunoRaw !== '' ? $matriculaAlunoRaw : null
                                ]);
                            }
                        }
                    }
                    $notice = 'Registro criado com sucesso.';
                }
            }
        }
    }
}

$alunosSelect = [];
if ($canManage && $hasAlunos) {
    if ($userIsSme || !$hasTurmas || !$hasTurmaAlunos) {
        $resAlunos = $conn->query("
            SELECT MIN(id) AS id, MAX(nome) AS nome, matricula
            FROM alunos
            GROUP BY matricula
            ORDER BY nome
        ");
    } elseif (!empty($userUnits)) {
        $sqlAlunos = "
            SELECT MIN(a.id) AS id, MAX(a.nome) AS nome, a.matricula
            FROM alunos a
            INNER JOIN turma_alunos ta ON ta.aluno_id = a.matricula
            INNER JOIN turmas t ON t.id = ta.turma_id
            WHERE t.id_escola IN (" . ph_list(count($userUnits)) . ")
            GROUP BY a.matricula
            ORDER BY nome
        ";
        $resAlunos = mysqli_execute_query($conn, $sqlAlunos, $userUnits);
    } else {
        $resAlunos = false;
    }
    if ($resAlunos) {
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
        $resMonitores = $conn->query("SELECT matricula, nome FROM usuarios WHERE ativo = 1 ORDER BY nome");
    } elseif (!empty($userUnits)) {
        $sqlMonitores = "
            SELECT u.matricula, u.nome
            FROM usuarios u
            INNER JOIN vinculo v ON v.matricula = u.matricula
            WHERE u.ativo = 1 AND v.id_unidade IN (" . ph_list(count($userUnits)) . ")
            GROUP BY u.matricula, u.nome
            ORDER BY u.nome
        ";
        $resMonitores = mysqli_execute_query($conn, $sqlMonitores, $userUnits);
    } else {
        $resMonitores = false;
    }
    if ($resMonitores) {
        while ($r = mysqli_fetch_assoc($resMonitores)) {
            $monitoresSelect[] = $r;
        }
    }
}

$rows = [];
if ($hasAee && $schemaOk) {
    $joinAlunos = $hasAlunoIdInAee
        ? "LEFT JOIN alunos a ON a.id = aa.aluno_id"
        : "LEFT JOIN alunos a ON a.matricula = aa.matricula_usuario";

    if ($monitorColumn === 'monitor_matricula') {
        $selectMonitor = ", aa.monitor_matricula, um.nome AS monitor_nome";
        $joinMonitor = "LEFT JOIN usuarios um ON um.matricula = aa.monitor_matricula";
    } elseif ($hasMonitorField) {
        $selectMonitor = ", aa.`{$monitorColumn}` AS monitor_nome";
        $joinMonitor = "";
    } else {
        $selectMonitor = "";
        $joinMonitor = "";
    }

    if ($userIsSme) {
        $sqlList = "
            SELECT aa.*, COALESCE(a.matricula, aa.matricula_usuario) AS matricula_aluno, a.nome AS aluno_nome_base
                   {$selectMonitor},
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
        $resRows = $conn->query($sqlList);
    } elseif (!empty($userUnits)) {
        $sqlList = "
            SELECT aa.*, COALESCE(a.matricula, aa.matricula_usuario) AS matricula_aluno, a.nome AS aluno_nome_base
                   {$selectMonitor},
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
        $resRows = mysqli_execute_query($conn, $sqlList, $userUnits);
    } else {
        $resRows = false;
    }
    if ($resRows) {
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
    <?php if ($notice): ?><div class="alert alert-success"><?= h_inclusao($notice) ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h_inclusao($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h5 class="card-title mb-3"><?= $editRow ? 'Editar registro AEE' : 'Novo registro AEE' ?></h5>
                <form method="post" class="row g-3">
                    <input type="hidden" name="id" value="<?= (int)($editRow['id'] ?? 0) ?>">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Aluno</label>
                        <select class="form-select" name="aluno_id" required>
                            <option value="">Selecione</option>
                            <?php foreach ($alunosSelect as $a): ?>
                                <?php
                                    $editAlunoId = (int)($editRow['aluno_id'] ?? 0);
                                    $editMatricula = (string)($editRow['matricula_usuario'] ?? '');
                                    $sel = ($editAlunoId > 0 && $editAlunoId === (int)$a['id'])
                                        || ($editAlunoId === 0 && $editMatricula !== '' && $editMatricula === (string)$a['matricula'])
                                        ? 'selected'
                                        : '';
                                ?>
                                <option value="<?= (int)$a['id'] ?>" <?= $sel ?>><?= h_inclusao($a['nome']) ?> (<?= h_inclusao($a['matricula'] ?? '-') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="serie" value="<?= h_inclusao($editRow['serie'] ?? '') ?>">
                    <input type="hidden" name="data_nascimento" value="<?= h_inclusao($editRow['data_nascimento'] ?? '') ?>">
                    <div class="col-12 col-md-6">
                        <label class="form-label">CID</label>
                        <input type="text" class="form-control" name="cid" value="<?= h_inclusao($editRow['cid'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Descrição</label>
                        <input type="text" class="form-control" name="descricao_outro" value="<?= h_inclusao($editRow['descricao_outro'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-3 form-check">
                        <input class="form-check-input" type="checkbox" name="diagnostico_fechado" id="diag" <?= ((int)($editRow['diagnostico_fechado'] ?? 0) === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="diag">Diagnóstico fechado</label>
                    </div>
                    <div class="col-6 col-md-3 form-check">
                        <input class="form-check-input" type="checkbox" name="suspeita" id="suspeita" <?= ((int)($editRow['suspeita'] ?? 0) === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="suspeita">Suspeita</label>
                    </div>
                    <div class="col-6 col-md-3 form-check">
                        <input class="form-check-input" type="checkbox" name="frequenta_teabraca" id="teabraca" <?= ((int)($editRow['frequenta_teabraca'] ?? 0) === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="teabraca">TEAbraça</label>
                    </div>
                    <?php if ($hasParticipaAee): ?>
                        <div class="col-6 col-md-4 form-check">
                            <input class="form-check-input" type="checkbox" name="participa_aee" id="participa_aee" <?= ((int)($editRow['participa_aee'] ?? 0) === 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="participa_aee">Participa do Atendimento Educacional Especializado</label>
                        </div>
                    <?php endif; ?>
                    <div class="col-6 col-md-3 form-check">
                        <input class="form-check-input" type="checkbox" name="monitor_exclusivo" id="monitor" <?= ((int)($editRow['monitor_exclusivo'] ?? 0) === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="monitor">Monitor exclusivo</label>
                    </div>
                    <?php if ($hasMonitorField): ?>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Usuário monitor</label>
                            <select class="form-select" name="monitor_ref">
                                <option value="">Selecione</option>
                                <?php foreach ($monitoresSelect as $m): ?>
                                    <?php
                                        if ($monitorColumn === 'monitor_matricula') {
                                            $selMonitor = ((int)($editRow['monitor_matricula'] ?? 0) === (int)$m['matricula']) ? 'selected' : '';
                                            $valorMonitor = (string)(int)$m['matricula'];
                                        } else {
                                            $selMonitor = ((string)($editRow[$monitorColumn] ?? '') === (string)$m['nome']) ? 'selected' : '';
                                            $valorMonitor = (string)$m['nome'];
                                        }
                                    ?>
                                    <option value="<?= h_inclusao($valorMonitor) ?>" <?= $selMonitor ?>>
                                        <?= h_inclusao($m['nome']) ?> (<?= (int)$m['matricula'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?= $editRow ? 'Salvar alterações' : 'Criar registro' ?></button>
                        <?php if ($editRow): ?><a href="app.php?page=alunos_inclusao" class="btn btn-outline-secondary ms-2">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Registros AEE</h5>
            <?php if (empty($rows)): ?>
                <p class="text-muted mb-0">Nenhum registro encontrado para sua escola.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Matrícula</th>
                                <th>Escola</th>
                                <th>CID</th>
                                <th>Diag.</th>
                                <th>Suspeita</th>
                                <th>TEAbraça</th>
                                <?php if ($hasParticipaAee): ?><th>Atendimento AEE</th><?php endif; ?>
                                <th>Monitor</th>
                                <?php if ($hasMonitorField): ?><th>Usuário monitor</th><?php endif; ?>
                                <?php if ($canManage): ?><th></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
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
                                    <?php if ($hasMonitorField): ?>
                                        <td><?= h_inclusao(($row['monitor_nome'] ?? '') !== '' ? $row['monitor_nome'] : '-') ?></td>
                                    <?php endif; ?>
                                    <?php if ($canManage): ?>
                                        <td><a class="btn btn-sm btn-outline-primary" href="app.php?page=alunos_inclusao&edit=<?= (int)$row['id'] ?>">Editar</a></td>
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
