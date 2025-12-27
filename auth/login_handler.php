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
header('Location: ../app.php?page=home');
exit;