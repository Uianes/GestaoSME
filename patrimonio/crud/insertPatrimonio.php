<?php
require_once __DIR__ . '/../auth_guard.php';
require_once '../db_connection.php';

function pat_post_string(string $key): ?string
{
  $value = trim((string)($_POST[$key] ?? ''));
  return $value !== '' ? $value : null;
}

function pat_supports_column(mysqli $conn, string $column): bool
{
  return function_exists('pat_column_exists') && pat_column_exists($conn, 'patrimonio', $column);
}

function pat_generate_provisional_number(): string
{
  return 'PROV-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function pat_handle_invoice_upload(): ?string
{
  if (empty($_FILES['notaFiscalAnexo']) || (int)$_FILES['notaFiscalAnexo']['error'] === UPLOAD_ERR_NO_FILE) {
    return null;
  }

  $file = $_FILES['notaFiscalAnexo'];
  if ((int)$file['error'] !== UPLOAD_ERR_OK) {
    throw new RuntimeException('Falha no upload do anexo da nota fiscal.');
  }

  $allowed = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
  ];
  $mime = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
  if (!isset($allowed[$mime])) {
    throw new RuntimeException('Anexo da nota fiscal inválido. Envie PDF, JPG, PNG ou WEBP.');
  }

  $dir = __DIR__ . '/../../uploads/patrimonio_notas';
  if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    throw new RuntimeException('Não foi possível criar a pasta de anexos do patrimônio.');
  }

  $filename = 'nota_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
  $target = $dir . '/' . $filename;
  if (!move_uploaded_file($file['tmp_name'], $target)) {
    throw new RuntimeException('Não foi possível salvar o anexo da nota fiscal.');
  }

  return 'uploads/patrimonio_notas/' . $filename;
}

$numeroPatrimonioInformado = pat_post_string('numeroPatrimonio');
$descricao = pat_post_string('descricao');
$dataEntrada = pat_post_string('dataEntrada');
$localizacao = !empty($_POST['localizacao']) ? (int)$_POST['localizacao'] : null;
$descricaoLocalizacao = pat_post_string('descricaoLocalizacao');
$status = pat_post_string('status');
$memorando = pat_post_string('memorando');

$marca = pat_post_string('marca');
$modelo = pat_post_string('modelo');
$numeroSerie = pat_post_string('numeroSerie');
$cor = pat_post_string('cor');
$anoAquisicao = pat_post_string('anoAquisicao');
$nfeNumero = pat_post_string('nfeNumero');
$fornecedorNome = pat_post_string('fornecedorNome');
$fornecedorCnpj = pat_post_string('fornecedorCnpj');
$valorUnitario = pat_post_string('valorUnitario');
$valorTotalNota = pat_post_string('valorTotalNota');
$emUso = isset($_POST['emUso']) && ($_POST['emUso'] === '0' || $_POST['emUso'] === '1') ? (int)$_POST['emUso'] : null;
$estadoConservacao = pat_post_string('estadoConservacao');
$origemAquisicao = pat_post_string('origemAquisicao');

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
  $_SESSION['message'] = "Você não tem permissão para cadastrar neste local.";
  $_SESSION['message_type'] = 'error';
  header('Location: ../index.php');
  exit;
}

if (!$descricao || !$dataEntrada || !$localizacao || !$descricaoLocalizacao || !$status) {
  $_SESSION['message'] = "Descrição, data de entrada, localização, descrição da localização e status são obrigatórios.";
  $_SESSION['message_type'] = 'error';
  header('Location: ../index.php');
  exit;
}

if ($status === 'Tombado') {
  $memorando = null;
}

if ($status === 'Descarte' && !$memorando) {
  $_SESSION['message'] = "O memorando não pode ser nulo para descarte.";
  $_SESSION['message_type'] = 'error';
  header('Location: ../index.php');
  exit;
}

try {
  $conn = open_connection();
  $numeroPatrimonio = $numeroPatrimonioInformado ?: pat_generate_provisional_number();
  $numeroProvisorio = $numeroPatrimonioInformado ? 0 : 1;

  $sqlCheck = "SELECT COUNT(*) as total FROM patrimonio WHERE N_Patrimonio = ?";
  $resultCheck = mysqli_execute_query($conn, $sqlCheck, [$numeroPatrimonio]);
  $row = mysqli_fetch_assoc($resultCheck);
  if ((int)($row['total'] ?? 0) > 0) {
    $_SESSION['message'] = "O patrimônio $numeroPatrimonio já foi cadastrado!";
    $_SESSION['message_type'] = 'error';
    close_connection($conn);
    header('Location: ../index.php');
    exit;
  }

  $notaFiscalAnexo = pat_handle_invoice_upload();

  $fields = [
    'N_Patrimonio' => $numeroPatrimonio,
    'Descricao' => $descricao,
    'Data_Entrada' => $dataEntrada,
    'Localizacao' => $localizacao,
    'Descricao_Localizacao' => $descricaoLocalizacao,
    'Status' => $status,
    'Memorando' => $memorando
  ];

  if (pat_supports_column($conn, 'numero_provisorio')) {
    $fields['numero_provisorio'] = $numeroProvisorio;
  }
  if (pat_supports_column($conn, 'marca')) {
    $fields['marca'] = $marca;
  }
  if (pat_supports_column($conn, 'modelo')) {
    $fields['modelo'] = $modelo;
  }
  if (pat_supports_column($conn, 'numero_serie')) {
    $fields['numero_serie'] = $numeroSerie;
  }
  if (pat_supports_column($conn, 'cor')) {
    $fields['cor'] = $cor;
  }
  if (pat_supports_column($conn, 'ano_aquisicao')) {
    $fields['ano_aquisicao'] = $anoAquisicao !== null ? (int)$anoAquisicao : null;
  }
  if (pat_supports_column($conn, 'nfe_numero')) {
    $fields['nfe_numero'] = $nfeNumero;
  }
  if (pat_supports_column($conn, 'fornecedor_nome')) {
    $fields['fornecedor_nome'] = $fornecedorNome;
  }
  if (pat_supports_column($conn, 'fornecedor_cnpj')) {
    $fields['fornecedor_cnpj'] = $fornecedorCnpj;
  }
  if (pat_supports_column($conn, 'valor_unitario')) {
    $fields['valor_unitario'] = $valorUnitario !== null ? (float)$valorUnitario : null;
  }
  if (pat_supports_column($conn, 'valor_total_nota')) {
    $fields['valor_total_nota'] = $valorTotalNota !== null ? (float)$valorTotalNota : null;
  }
  if (pat_supports_column($conn, 'nota_fiscal_anexo')) {
    $fields['nota_fiscal_anexo'] = $notaFiscalAnexo;
  }
  if (pat_supports_column($conn, 'em_uso')) {
    $fields['em_uso'] = $emUso;
  }
  if (pat_supports_column($conn, 'estado_conservacao')) {
    $fields['estado_conservacao'] = $estadoConservacao;
  }
  if (pat_supports_column($conn, 'origem_aquisicao')) {
    $fields['origem_aquisicao'] = $origemAquisicao;
  }
  if (pat_supports_column($conn, 'cadastrado_por')) {
    $fields['cadastrado_por'] = (int)($_SESSION['user']['matricula'] ?? 0);
  }
  if (pat_supports_column($conn, 'cadastrado_em')) {
    $fields['cadastrado_em'] = date('Y-m-d H:i:s');
  }
  if (pat_supports_column($conn, 'unidade_vinculada_cadastro')) {
    $fields['unidade_vinculada_cadastro'] = $localizacao;
  }

  $sql = "INSERT INTO patrimonio (" . implode(', ', array_keys($fields)) . ") VALUES (" . implode(', ', array_fill(0, count($fields), '?')) . ")";
  mysqli_execute_query($conn, $sql, array_values($fields));

  $_SESSION['message'] = $numeroProvisorio === 1
    ? "Patrimônio cadastrado com número provisório '$numeroPatrimonio'."
    : "O patrimônio '$numeroPatrimonio' foi cadastrado com sucesso!";
  $_SESSION['message_type'] = 'success';
} catch (Throwable $e) {
  $_SESSION['message'] = "Erro ao cadastrar patrimônio: " . $e->getMessage();
  $_SESSION['message_type'] = 'error';
}

if (isset($conn)) {
  close_connection($conn);
}
header('Location: ../index.php');
?>
