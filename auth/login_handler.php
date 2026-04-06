<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/first_access.php';

function login_fail(string $message)
{
    $_SESSION['flash_error'] = $message;
    header('Location: ../app.php?page=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../app.php?page=login');
    exit;
}
$usuario = trim($_POST['usuario'] ?? '');
$senha   = (string)($_POST['senha'] ?? '');
if ($usuario === '' || $senha === '') {
    login_fail('Informe usuário e senha.');
}

function is_sme_unit_name(string $name): bool
{
    $normalized = preg_replace('/[^A-Z]/', '', strtoupper($name));
    return $normalized === 'SME';
}

function login_prepare_user_query(mysqli $conn)
{
    $telefoneSelect = first_access_has_column($conn, 'usuarios', 'telefone') ? 'telefone' : "'' AS telefone";
    $avatarSelect = first_access_has_column($conn, 'usuarios', 'avatar') ? 'avatar' : "'' AS avatar";
    $admSelect = first_access_has_column($conn, 'usuarios', 'ADM') ? 'ADM' : '0 AS ADM';

    $sql = "SELECT matricula, cpf, nome, email, {$telefoneSelect}, senha, ativo, {$avatarSelect}, {$admSelect}
            FROM usuarios
            WHERE cpf = ?
               OR email = ?
               OR matricula = ?
            LIMIT 1";

    return $conn->prepare($sql);
}

function login_load_user_units(mysqli $conn, int $matricula): array
{
    $stmt = $conn->prepare("
        SELECT DISTINCT vinculo.id_unidade, unidade.nome AS unidade_nome, orgaos.nome_orgao
        FROM vinculo
        INNER JOIN unidade ON vinculo.id_unidade = unidade.id_unidade
        INNER JOIN orgaos ON vinculo.id_orgao = orgaos.id_orgao
        WHERE vinculo.matricula = ?
        ORDER BY unidade.nome ASC
    ");

    if ($stmt) {
        $stmt->bind_param("i", $matricula);
        $stmt->execute();
        $dbIdUnidade = 0;
        $dbNomeUnidade = '';
        $dbNomeOrgao = '';
        $stmt->bind_result($dbIdUnidade, $dbNomeUnidade, $dbNomeOrgao);

        $userUnidades = [];
        $userUnidadesNomes = [];
        $userIsSme = false;
        while ($stmt->fetch()) {
            $idUnidade = (int)$dbIdUnidade;
            $nomeUnidade = (string)$dbNomeUnidade;
            if ($idUnidade > 0) {
                $userUnidades[] = $idUnidade;
                $userUnidadesNomes[$idUnidade] = $nomeUnidade;
            }
            $nomeOrgao = (string)($dbNomeOrgao ?? '');
            if (is_sme_unit_name($nomeUnidade) || is_sme_unit_name($nomeOrgao)) {
                $userIsSme = true;
            }
        }
        $stmt->close();

        return [
            'ids' => $userUnidades,
            'names' => $userUnidadesNomes,
            'is_sme' => $userIsSme,
        ];
    }

    $fallbackStmt = $conn->prepare("
        SELECT DISTINCT vinculo.id_unidade, unidade.nome AS unidade_nome
        FROM vinculo
        INNER JOIN unidade ON vinculo.id_unidade = unidade.id_unidade
        WHERE vinculo.matricula = ?
        ORDER BY unidade.nome ASC
    ");

    if (!$fallbackStmt) {
        return [
            'ids' => [],
            'names' => [],
            'is_sme' => false,
        ];
    }

    $fallbackStmt->bind_param("i", $matricula);
    $fallbackStmt->execute();
    $dbIdUnidade = 0;
    $dbNomeUnidade = '';
    $fallbackStmt->bind_result($dbIdUnidade, $dbNomeUnidade);

    $userUnidades = [];
    $userUnidadesNomes = [];
    while ($fallbackStmt->fetch()) {
        $idUnidade = (int)$dbIdUnidade;
        $nomeUnidade = (string)$dbNomeUnidade;
        if ($idUnidade > 0) {
            $userUnidades[] = $idUnidade;
            $userUnidadesNomes[$idUnidade] = $nomeUnidade;
        }
    }
    $fallbackStmt->close();

    return [
        'ids' => $userUnidades,
        'names' => $userUnidadesNomes,
        'is_sme' => false,
    ];
}

try {
    $conn = db();
    $usuario_num = preg_replace('/\D+/', '', $usuario);
    $matricula = ctype_digit($usuario_num) ? (int)$usuario_num : 0;
    $stmt = login_prepare_user_query($conn);
    if (!$stmt) {
        login_fail('Falha ao preparar a autenticação.');
    }
    $stmt->bind_param("ssi", $usuario_num, $usuario, $matricula);
    $stmt->execute();
    $dbMatricula = 0;
    $dbCpf = '';
    $dbNome = '';
    $dbEmail = '';
    $dbTelefone = '';
    $dbSenha = '';
    $dbAtivo = 0;
    $dbAvatar = '';
    $dbAdm = 0;
    $stmt->bind_result($dbMatricula, $dbCpf, $dbNome, $dbEmail, $dbTelefone, $dbSenha, $dbAtivo, $dbAvatar, $dbAdm);
    $user = null;
    if ($stmt->fetch()) {
        $user = [
            'matricula' => (int)$dbMatricula,
            'cpf' => (string)$dbCpf,
            'nome' => (string)$dbNome,
            'email' => (string)($dbEmail ?? ''),
            'telefone' => (string)($dbTelefone ?? ''),
            'senha' => (string)$dbSenha,
            'ativo' => (int)$dbAtivo,
            'avatar' => (string)($dbAvatar ?? ''),
            'ADM' => (int)($dbAdm ?? 0),
        ];
    }
    $stmt->close();
    if (!$user) {
        login_fail('Usuário não encontrado.');
    }
    if ((int)$user['ativo'] !== 1) {
        login_fail('Usuário inativo.');
    }
    $stored = (string)$user['senha'];
    $ok = false;
    if (strpos($stored, '$2y$') === 0 || strpos($stored, '$argon2') === 0) {
        $ok = password_verify($senha, $stored);
    } else {
        $ok = hash_equals($stored, hash('sha256', $senha));
    }
    if (!$ok) {
        login_fail('Senha inválida.');
    }
    $_SESSION['user'] = [
        'matricula' => (int)$user['matricula'],
        'cpf'       => (string)$user['cpf'],
        'nome'      => (string)$user['nome'],
        'email'     => (string)($user['email'] ?? ''),
        'telefone'  => (string)($user['telefone'] ?? ''),
        'avatar'    => (string)($user['avatar'] ?? ''),
        'adm'       => (int)($user['ADM'] ?? 0)
    ];
    $userUnits = login_load_user_units($conn, (int)$_SESSION['user']['matricula']);
    $userUnidades = $userUnits['ids'];
    $userUnidadesNomes = $userUnits['names'];
    $_SESSION['user_unidades'] = $userUnidades;
    $_SESSION['user_unidades_names'] = $userUnidadesNomes;
    $_SESSION['user_is_sme'] = (bool)$userUnits['is_sme'];

    if (!empty($userUnidades)) {
        $_SESSION['user_local'] = $userUnidades[0];
        $_SESSION['user_local_name'] = $userUnidadesNomes[$userUnidades[0]] ?? null;
    } else {
        $_SESSION['user_local'] = null;
        $_SESSION['user_local_name'] = null;
    }
    if (first_access_needs_update((int)$user['matricula'])) {
        header('Location: ../app.php?page=primeiro_acesso');
        exit;
    }
    header('Location: ../app.php?page=home');
    exit;
} catch (Throwable $e) {
    error_log('Login failure: ' . $e->getMessage());
    login_fail('Erro interno no login. Verifique a configuração do servidor.');
}
