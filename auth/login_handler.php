<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../app.php?page=login');
    exit;
}
$usuario = trim($_POST['usuario'] ?? '');
$senha   = (string)($_POST['senha'] ?? '');
if ($usuario === '' || $senha === '') {
    $_SESSION['flash_error'] = 'Informe usuário e senha.';
    header('Location: ../app.php?page=login');
    exit;
}
$conn = db();
$usuario_num = preg_replace('/\D+/', '', $usuario);
$matricula = ctype_digit($usuario_num) ? (int)$usuario_num : 0;
$sql = "SELECT matricula, cpf, nome, email, senha, ativo, avatar
        FROM usuarios
        WHERE cpf = ?
           OR email = ?
           OR matricula = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $usuario_num, $usuario, $matricula);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
if (!$user) {
    $_SESSION['flash_error'] = 'Usuário não encontrado.';
    header('Location: ../app.php?page=login');
    exit;
}
if ((int)$user['ativo'] !== 1) {
    $_SESSION['flash_error'] = 'Usuário inativo.';
    header('Location: ../app.php?page=login');
    exit;
}
$stored = (string)$user['senha'];
$ok = false;
if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2')) {
    $ok = password_verify($senha, $stored);
} else {
    $ok = hash_equals($stored, hash('sha256', $senha));
}
if (!$ok) {
    $_SESSION['flash_error'] = 'Senha inválida.';
    header('Location: ../app.php?page=login');
    exit;
}
$_SESSION['user'] = [
    'matricula' => (int)$user['matricula'],
    'cpf'       => (string)$user['cpf'],
    'nome'      => (string)$user['nome'],
    'email'     => (string)($user['email'] ?? ''),
    'avatar'    => (string)($user['avatar'] ?? '')
];
$stmt = $conn->prepare("
    SELECT DISTINCT vinculo.id_unidade, unidade.nome AS unidade_nome, orgaos.nome_orgao
    FROM vinculo
    INNER JOIN unidade ON vinculo.id_unidade = unidade.id_unidade
    INNER JOIN orgaos ON vinculo.id_orgao = orgaos.id_orgao
    WHERE vinculo.matricula = ?
    ORDER BY unidade.nome ASC
");
$stmt->bind_param("i", $_SESSION['user']['matricula']);
$stmt->execute();
$resultVinculos = $stmt->get_result();
$stmt->close();

$userUnidades = [];
$userUnidadesNomes = [];
$userIsSme = false;
function is_sme_unit_name(string $name): bool {
    $normalized = preg_replace('/[^A-Z]/', '', strtoupper($name));
    return $normalized === 'SME';
}
while ($row = $resultVinculos->fetch_assoc()) {
    $idUnidade = (int)$row['id_unidade'];
    $nomeUnidade = (string)$row['unidade_nome'];
    if ($idUnidade > 0) {
        $userUnidades[] = $idUnidade;
        $userUnidadesNomes[$idUnidade] = $nomeUnidade;
    }
    $nomeOrgao = (string)($row['nome_orgao'] ?? '');
    if (is_sme_unit_name($nomeUnidade) || is_sme_unit_name($nomeOrgao)) {
        $userIsSme = true;
    }
}

$_SESSION['user_unidades'] = $userUnidades;
$_SESSION['user_unidades_names'] = $userUnidadesNomes;
$_SESSION['user_is_sme'] = $userIsSme;

if (!empty($userUnidades)) {
    $_SESSION['user_local'] = $userUnidades[0];
    $_SESSION['user_local_name'] = $userUnidadesNomes[$userUnidades[0]] ?? null;
} else {
    $_SESSION['user_local'] = null;
    $_SESSION['user_local_name'] = null;
}
header('Location: ../app.php?page=home');
exit;
