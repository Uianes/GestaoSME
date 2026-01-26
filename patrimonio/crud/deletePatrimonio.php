<?php
require_once __DIR__ . '/../auth_guard.php';
require_once '../db_connection.php';

$idPatrimonioExcluir = !empty($_POST['idPatrimonioExcluir']) ? $_POST['idPatrimonioExcluir'] : NULL;

if (!$idPatrimonioExcluir) {
  $_SESSION['message'] = "O campo Nº Patrimônio é obrigatório.";
  $_SESSION['message_type'] = 'error';
  header('Location: ../index.php');
  exit;
}

try {
  $conn = open_connection();

  $sqlLocal = "SELECT Localizacao FROM patrimonio WHERE N_Patrimonio = ?";
  $resultadoLocal = mysqli_execute_query($conn, $sqlLocal, [$idPatrimonioExcluir]);
  $rowLocal = mysqli_fetch_assoc($resultadoLocal);
  
  if (!$rowLocal) {
    $_SESSION['message'] = "O patrimônio $idPatrimonioExcluir não foi encontrado!";
    $_SESSION['message_type'] = 'error';
    close_connection($conn);
    header('Location: ../index.php');
    exit;
  }

  $localizacao = (int)$rowLocal['Localizacao'];
  [$userIsSme, $userUnidades] = pat_user_context();
  $userLocal = $_SESSION['user_local'] ?? null;
  if ($userLocal === null && !empty($userUnidades)) {
    $userLocal = $userUnidades[0];
  }
  if ($userLocal === null && !$userIsSme) {
    $_SESSION['message'] = "Sessão inválida.";
    $_SESSION['message_type'] = 'error';
    close_connection($conn);
    header('Location: ../index.php');
    exit;
  }
  if (!$userIsSme && !in_array($localizacao, array_map('intval', $userUnidades), true)) {
    $_SESSION['message'] = "Você não tem permissão para excluir este patrimonio.";
    $_SESSION['message_type'] = 'error';
    close_connection($conn);
    header('Location: ../index.php');
    exit;
  }

  $sqlCheck = "SELECT COUNT(*) as total FROM patrimonio WHERE N_Patrimonio = ?";
  $resultCheck = mysqli_execute_query($conn, $sqlCheck, [$idPatrimonioExcluir]);
  $row = mysqli_fetch_assoc($resultCheck);
  if ($row['total'] == 0) {
    $_SESSION['message'] = "O patrimônio $idPatrimonioExcluir não foi encontrado!";
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
  $sql = "DELETE FROM patrimonio WHERE N_Patrimonio = ?";
  $resultDelete = mysqli_execute_query($conn, $sql, [$idPatrimonioExcluir]);
  if ($resultDelete && mysqli_affected_rows($conn) > 0) {
    $_SESSION['message'] = "O patrimônio '$idPatrimonioExcluir' foi excluído com sucesso!";
    $_SESSION['message_type'] = 'success';
  } else {
    $_SESSION['message'] = "Nenhum patrimônio foi excluído. Verifique permissões ou se o registro existe.";
    $_SESSION['message_type'] = 'error';
  }
} catch (Exception $e) {
  $_SESSION['message'] = "Erro ao excluir patrimônio: " . $e->getMessage();
  $_SESSION['message_type'] = 'error';
}

if (isset($conn)) {close_connection($conn);}
header('Location: ../index.php');
?>
