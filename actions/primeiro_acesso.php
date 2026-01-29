<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/first_access.php';

require_login();

$matricula = (int)($_SESSION['user']['matricula'] ?? 0);
if ($matricula <= 0) {
    $_SESSION['flash_error'] = 'Sessão inválida. Faça login novamente.';
    header('Location: ../app.php?page=login');
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));
$telefone = trim((string)($_POST['telefone'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');
$confirmacao = (string)($_POST['senha_confirmacao'] ?? '');

if ($email === '' || $telefone === '' || $senha === '' || $confirmacao === '') {
    $_SESSION['flash_error'] = 'Preencha todos os campos.';
    header('Location: ../app.php?page=primeiro_acesso');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Informe um e-mail válido.';
    header('Location: ../app.php?page=primeiro_acesso');
    exit;
}

if ($senha !== $confirmacao) {
    $_SESSION['flash_error'] = 'As senhas não conferem.';
    header('Location: ../app.php?page=primeiro_acesso');
    exit;
}

if (strlen($senha) < 6) {
    $_SESSION['flash_error'] = 'A senha deve ter pelo menos 6 caracteres.';
    header('Location: ../app.php?page=primeiro_acesso');
    exit;
}

$ok = first_access_update_user($matricula, $email, $telefone, $senha);
if (!$ok) {
    $_SESSION['flash_error'] = 'Não foi possível atualizar seus dados.';
    header('Location: ../app.php?page=primeiro_acesso');
    exit;
}

$_SESSION['user']['email'] = $email;

header('Location: ../app.php?page=home');
exit;
