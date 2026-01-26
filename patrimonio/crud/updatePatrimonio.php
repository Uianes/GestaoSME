<?php
require_once __DIR__ . '/../auth_guard.php';
require_once '../db_connection.php';

$numeroPatrimonio = !empty($_POST['numeroPatrimonio']) ? $_POST['numeroPatrimonio'] : NULL;
$descricao = !empty($_POST['descricao']) ? $_POST['descricao'] : NULL;
$dataEntrada = !empty($_POST['dataEntrada']) ? $_POST['dataEntrada'] : NULL;
$localizacao = !empty($_POST['localizacao']) ? (int)$_POST['localizacao'] : NULL;
$descricaoLocalizacao = !empty($_POST['DescricaoLocalizacaoEditar']) ? $_POST['DescricaoLocalizacaoEditar'] : NULL;

[$userIsSme, $userUnidades] = pat_user_context();
$userLocal = $_SESSION['user_local'] ?? null;
if ($userLocal === null && !empty($userUnidades)) {
  $userLocal = $userUnidades[0];
}
if ($userLocal === null && !$userIsSme) {
  $_SESSION['message'] = "Sessão inválida.";
  $_SESSION['message_type'] = 'error';
  header('Location: ../index.php');
  exit;
}
if (!$userIsSme && !in_array((int)$localizacao, array_map('intval', $userUnidades), true)) {
  $_SESSION['message'] = "Você não tem permissão para editar este patrimonio.";
  $_SESSION['message_type'] = 'error';
  header('Location: ../index.php');
  exit;
}

if (!$numeroPatrimonio || !$descricao || !$dataEntrada || !$localizacao || !$descricaoLocalizacao) {
  $_SESSION['message'] = "Todos os campos são obrigatórios.";
  $_SESSION['message_type'] = 'error';
  header('Location: ../index.php');
  exit;
}

try {
  $conn = open_connection();
  $sqlCheck = "SELECT COUNT(*) as total FROM patrimonio WHERE N_Patrimonio = ?";
  $resultCheck = mysqli_execute_query($conn, $sqlCheck, [$numeroPatrimonio]);
  $row = mysqli_fetch_assoc($resultCheck);
  if ($row['total'] == 0) {
    $_SESSION['message'] = "O patrimônio $numeroPatrimonio não foi encontrado!";
    $_SESSION['message_type'] = 'error';
    close_connection($conn);
    header('Location: ../index.php');
    exit;
  }
} catch (Exception $e) {
  $_SESSION['message'] = "Erro ao verificar patrimônio: " . $e->getMessage();
  $_SESSION['message_type'] = 'error';
  if (isset($conn)) {close_connection($conn);}
  header('Location: ../index.php');
  exit;
}

try {
  $sql = "UPDATE patrimonio SET Descricao = ?, Data_Entrada = ?, Localizacao = ?, Descricao_Localizacao = ? WHERE N_Patrimonio = ?";
  mysqli_execute_query($conn, $sql, [
    $descricao,
    $dataEntrada,
    $localizacao,
    $descricaoLocalizacao,
    $numeroPatrimonio
  ]);
  $_SESSION['message'] = "O patrimônio '$numeroPatrimonio' foi atualizado com sucesso!";
  $_SESSION['message_type'] = 'success';
} catch (Exception $e) {
  $_SESSION['message'] = "Erro ao atualizar patrimônio: " . $e->getMessage();
  $_SESSION['message_type'] = 'error';
}

if (isset($conn)) {close_connection($conn);}
header('Location: ../index.php');
?>
