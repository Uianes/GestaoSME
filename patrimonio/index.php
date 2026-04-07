<?php
$bootError = null;
$conn = null;
$unidades = [];
$unidades_error = null;
$userIsSme = false;
$userUnidades = [];
$schemaAdditionsAlert = null;

try {
  require_once __DIR__ . '/auth_guard.php';
} catch (Throwable $e) {
  $bootError = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reload'])) {
  header("Location: index.php");
  exit;
}

$toastHtml = '';
if (isset($_SESSION['message'])) {
  $toastClass = isset($_SESSION['message_type']) && $_SESSION['message_type'] == 'success' ? 'text-bg-success' : 'text-bg-danger';
  $toastHtml = '<div class="toast-container top-0 start-50 translate-middle-x mt-2">
            <div id="toastMessage" class="toast align-items-center ' . $toastClass . ' border-0 w-auto" role="alert" aria-live="assertive" aria-atomic="true">
              <div class="d-flex">
                <div class="toast-body">' . $_SESSION['message'] . '</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
              </div>
            </div>
          </div>';
  unset($_SESSION['message']);
  unset($_SESSION['message_type']);
}

if ($bootError === null) {
  try {
    require_once 'db_connection.php';
    $conn = open_connection();
    [$userIsSme, $userUnidades] = pat_user_context($conn);

    $patrimonioExtraColumns = [
      'numero_provisorio',
      'marca',
      'modelo',
      'numero_serie',
      'cor',
      'ano_aquisicao',
      'nfe_numero',
      'fornecedor_nome',
      'fornecedor_cnpj',
      'valor_unitario',
      'valor_total_nota',
      'nota_fiscal_anexo',
      'em_uso',
      'estado_conservacao',
      'origem_aquisicao',
      'cadastrado_por',
      'cadastrado_em',
      'unidade_vinculada_cadastro'
    ];
    $missingPatrimonioColumns = [];
    if (function_exists('pat_table_exists') && function_exists('pat_column_exists') && pat_table_exists($conn, 'patrimonio')) {
      foreach ($patrimonioExtraColumns as $column) {
        if (!pat_column_exists($conn, 'patrimonio', $column)) {
          $missingPatrimonioColumns[] = $column;
        }
      }
    }
    if (!empty($missingPatrimonioColumns)) {
      $schemaAdditionsAlert = "ALTER TABLE patrimonio\n"
        . "  ADD COLUMN numero_provisorio TINYINT(1) NOT NULL DEFAULT 0,\n"
        . "  ADD COLUMN marca VARCHAR(120) NULL,\n"
        . "  ADD COLUMN modelo VARCHAR(120) NULL,\n"
        . "  ADD COLUMN numero_serie VARCHAR(120) NULL,\n"
        . "  ADD COLUMN cor VARCHAR(80) NULL,\n"
        . "  ADD COLUMN ano_aquisicao SMALLINT NULL,\n"
        . "  ADD COLUMN nfe_numero VARCHAR(60) NULL,\n"
        . "  ADD COLUMN fornecedor_nome VARCHAR(180) NULL,\n"
        . "  ADD COLUMN fornecedor_cnpj VARCHAR(18) NULL,\n"
        . "  ADD COLUMN valor_unitario DECIMAL(12,2) NULL,\n"
        . "  ADD COLUMN valor_total_nota DECIMAL(12,2) NULL,\n"
        . "  ADD COLUMN nota_fiscal_anexo VARCHAR(600) NULL,\n"
        . "  ADD COLUMN em_uso TINYINT(1) NULL,\n"
        . "  ADD COLUMN estado_conservacao VARCHAR(20) NULL,\n"
        . "  ADD COLUMN origem_aquisicao VARCHAR(40) NULL,\n"
        . "  ADD COLUMN cadastrado_por INT NULL,\n"
        . "  ADD COLUMN cadastrado_em DATETIME NULL,\n"
        . "  ADD COLUMN unidade_vinculada_cadastro INT NULL;";
    }

    if (function_exists('pat_table_exists') && function_exists('pat_column_exists')
      && pat_table_exists($conn, 'unidade')
      && pat_column_exists($conn, 'unidade', 'id_unidade')
      && pat_column_exists($conn, 'unidade', 'nome')) {
      $unidades_result = mysqli_query($conn, "SELECT id_unidade, nome FROM unidade ORDER BY nome");
      if ($unidades_result) {
        while ($row_unidade = mysqli_fetch_assoc($unidades_result)) {
          $unidades[] = [
            'id' => (int)$row_unidade['id_unidade'],
            'nome' => $row_unidade['nome'],
          ];
        }
      } else {
        $unidades_error = mysqli_error($conn);
      }
    } else {
      $unidades_error = 'Tabela unidade não encontrada ou schema incompatível.';
    }

    if (!$userIsSme && !empty($userUnidades)) {
      $unidades = array_values(array_filter($unidades, function ($unidade) use ($userUnidades) {
        return in_array((int)$unidade['id'], $userUnidades, true);
      }));
    }
  } catch (Throwable $e) {
    $bootError = $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="./assets/logoPrefeituraSA.png" type="image/x-icon">
  <title>Patrimônio</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>
  <?php echo $toastHtml; ?>
  <?php if ($bootError !== null): ?>
    <div class="container py-4">
      <div class="alert alert-danger mb-0">
        Falha ao carregar o módulo de patrimônio.
        <br>
        <small><?= htmlspecialchars($bootError) ?></small>
      </div>
    </div>
  </body>
</html>
<?php return; endif; ?>
  <nav class="navbar bg-body-secondary border-bottom sticky-top">
    <div class="container-fluid d-flex">
      <a class="navbar-brand" href="#">
        <img src="./assets/logoPrefeituraSA.png" alt="Logo Prefeitura" height="30" class="d-inline-block align-text-top">
      </a>
      <button class="btn btn-primary ms-3" type="button" data-bs-toggle="modal" data-bs-target="#ModalCadastrarPatrimonio">
        Cadastrar Patrimônio
      </button>
      <button class="btn btn-primary ms-3" type="button" data-bs-toggle="modal" data-bs-target="#ModalGerarPDF">
        Gerar PDF
      </button>
      <a class="btn btn-success ms-3" href="./export_xlsx.php" title="Baixar planilha XLSX com todos os patrimônios">
        Exportar XLSX
      </a>
      <!-- Formulário unificado de busca -->
      <form class="d-flex ms-auto" method="GET">
        <div class="input-group mx-1">
          <!-- Dropdown para filtrar por locais -->
          <select class="form-select" name="local_filtro">
            <option value="">Todos os Locais</option>
            <?php $local_filtro_value = $_GET['local_filtro'] ?? ''; ?>
            <?php foreach ($unidades as $unidade): ?>
              <option value="<?= (int)$unidade['id'] ?>" <?php echo ((string)$local_filtro_value === (string)$unidade['id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($unidade['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <!-- Campo de busca por patrimônio -->
          <input class="form-control" type="search" name="search" placeholder="Buscar patrimônio..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
          <button class="btn btn-success" type="submit" title="Buscar"><i class="bi bi-search"></i></button>
        </div>
      </form>
      <form method="POST">
        <button class="btn btn-primary" name="reload" type="submit" title='Recarregar tabela'><i class="bi bi-arrow-clockwise"></i></button>
      </form>
    </div>
  </nav>

  <div class="container-fluid">
    <?php if ($schemaAdditionsAlert !== null): ?>
      <div class="alert alert-info mt-3 mb-0">
        Para habilitar todos os novos campos do patrimônio, atualize a tabela com:
        <pre class="mb-0 mt-2 small"><code><?= htmlspecialchars($schemaAdditionsAlert, ENT_QUOTES, 'UTF-8') ?></code></pre>
      </div>
    <?php endif; ?>

    <!-- tabela -->
    <div class="row justify-content-center">
      <div class="col-10">
        <table class='table table-bordered mt-3 table-striped' id="table">
          <thead class="text-center">
            <tr>
              <th scope='col'>Nº Patrimônio</th>
              <th scope='col'>Descrição</th>
              <th scope='col'>Data Entrada</th>
              <th scope='col'>Localização</th>
              <th scope='col'>Descrição Localização</th>
              <th scope='col'>Status</th>
              <th scope='col'>Memorando</th>
              <th scope='col'>Ações</th>
            </tr>
          </thead>
          <tbody class='table-group-divider'>
            <?php
            try {
              $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
              $limit = 15;
              $search = isset($_GET['search']) ? trim($_GET['search']) : '';
              $local_filtro_value = $_GET['local_filtro'] ?? '';
              $local_filtro = $local_filtro_value !== '' ? (int)$local_filtro_value : 0;
              $offset = ($page - 1) * $limit;

              $where_conditions = [];
              if ($search !== '') {
                $search_esc = mysqli_real_escape_string($conn, $search);
                $where_conditions[] = "p.N_Patrimonio LIKE '%$search_esc%'";
              }
              if ($local_filtro > 0) {
                $where_conditions[] = "p.Localizacao = $local_filtro";
              }
              if (!$userIsSme) {
                if (count($userUnidades) > 0) {
                  $allowed = implode(',', $userUnidades);
                  $where_conditions[] = "p.Localizacao IN ($allowed)";
                } else {
                  $where_conditions[] = "1=0";
                }
              }
              $where = count($where_conditions) ? " WHERE " . implode(" AND ", $where_conditions) : "";

              $sql_count = "SELECT COUNT(*) AS total FROM patrimonio p" . $where;
              $result_count = mysqli_query($conn, $sql_count);
              if (!$result_count) {
                throw new Exception(mysqli_error($conn));
              }
              $row_count = mysqli_fetch_assoc($result_count);
              $total = $row_count['total'];
              $total_pages = ceil($total / $limit);

              $sql = "SELECT p.*, u.nome AS unidade_nome
                      FROM patrimonio p
                      LEFT JOIN unidade u ON u.id_unidade = p.Localizacao" . $where . " LIMIT $offset, $limit";
              $result = mysqli_query($conn, $sql);
              if (!$result) {
                throw new Exception(mysqli_error($conn));
              }

              if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                  $dataEntrada = date('d/m/Y', strtotime($row['Data_Entrada']));
                  $descricaoSemEnter = str_replace(["\r", "\n"], ' ', $row['Descricao']);
                  $descricaoLocalSemEnter = str_replace(["\r", "\n"], ' ', $row['Descricao_Localizacao']);
                  $isNumeroProvisorio = ((int)($row['numero_provisorio'] ?? 0) === 1)
                    || strpos((string)$row['N_Patrimonio'], 'PROV-') === 0;
                  echo "<tr>";
                  echo "<td>"
                    . htmlspecialchars((string)$row['N_Patrimonio'], ENT_QUOTES, 'UTF-8')
                    . ($isNumeroProvisorio ? " <span class='badge text-bg-warning'>Provisório</span>" : "")
                    . "</td>";
                  echo "<td>" . $row['Descricao'] . "</td>";
                  echo "<td>" . $dataEntrada . "</td>";
                  $localizacaoNome = $row['unidade_nome'] ?? $row['Localizacao'];
                  echo "<td>" . $localizacaoNome . "</td>";
                  echo "<td>" . $row['Descricao_Localizacao'] . "</td>";
                  echo "<td>" . $row['Status'] . "</td>";
                  echo "<td>" . $row['Memorando'] . "</td>";

                  if ($row['Status'] === 'Tombado') {
                    echo "<td class='text-center'>
                                    <button class='btn btn-secondary btn-sm' title='Visualizar'
                                      type='button'
                                      data-numero-original='" . htmlspecialchars((string)$row['N_Patrimonio'], ENT_QUOTES, 'UTF-8') . "'
                                      data-numero-patrimonio='" . htmlspecialchars((string)$row['N_Patrimonio'], ENT_QUOTES, 'UTF-8') . "'
                                      data-numero-provisorio='" . (((int)($row['numero_provisorio'] ?? 0) === 1 || strpos((string)$row['N_Patrimonio'], 'PROV-') === 0) ? "1" : "0") . "'
                                      data-descricao='" . htmlspecialchars((string)$row['Descricao'], ENT_QUOTES, 'UTF-8') . "'
                                      data-data-entrada='" . htmlspecialchars((string)$row['Data_Entrada'], ENT_QUOTES, 'UTF-8') . "'
                                      data-localizacao='" . htmlspecialchars((string)$row['Localizacao'], ENT_QUOTES, 'UTF-8') . "'
                                      data-unidade-nome='" . htmlspecialchars((string)$localizacaoNome, ENT_QUOTES, 'UTF-8') . "'
                                      data-desc-localizacao='" . htmlspecialchars((string)$row['Descricao_Localizacao'], ENT_QUOTES, 'UTF-8') . "'
                                      data-status='" . htmlspecialchars((string)$row['Status'], ENT_QUOTES, 'UTF-8') . "'
                                      data-memorando='" . htmlspecialchars((string)$row['Memorando'], ENT_QUOTES, 'UTF-8') . "'
                                      data-marca='" . htmlspecialchars((string)($row['marca'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-modelo='" . htmlspecialchars((string)($row['modelo'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-numero-serie='" . htmlspecialchars((string)($row['numero_serie'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-cor='" . htmlspecialchars((string)($row['cor'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-ano-aquisicao='" . htmlspecialchars((string)($row['ano_aquisicao'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-nfe-numero='" . htmlspecialchars((string)($row['nfe_numero'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-fornecedor-nome='" . htmlspecialchars((string)($row['fornecedor_nome'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-fornecedor-cnpj='" . htmlspecialchars((string)($row['fornecedor_cnpj'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-valor-unitario='" . htmlspecialchars((string)($row['valor_unitario'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-valor-total-nota='" . htmlspecialchars((string)($row['valor_total_nota'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-em-uso='" . htmlspecialchars((string)($row['em_uso'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-estado-conservacao='" . htmlspecialchars((string)($row['estado_conservacao'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-origem-aquisicao='" . htmlspecialchars((string)($row['origem_aquisicao'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-nota-fiscal-anexo='" . htmlspecialchars((string)($row['nota_fiscal_anexo'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      onclick='abrirModalVisualizar(this)'>
                                      <i class='bi bi-eye-fill'></i>
                                    </button>
                                    <button class='btn btn-primary btn-sm' title='Editar'
                                      type='button'
                                      data-numero-original='" . htmlspecialchars((string)$row['N_Patrimonio'], ENT_QUOTES, 'UTF-8') . "'
                                      data-numero-patrimonio='" . htmlspecialchars((string)$row['N_Patrimonio'], ENT_QUOTES, 'UTF-8') . "'
                                      data-numero-provisorio='" . (((int)($row['numero_provisorio'] ?? 0) === 1 || strpos((string)$row['N_Patrimonio'], 'PROV-') === 0) ? "1" : "0") . "'
                                      data-descricao='" . htmlspecialchars((string)$row['Descricao'], ENT_QUOTES, 'UTF-8') . "'
                                      data-data-entrada='" . htmlspecialchars((string)$row['Data_Entrada'], ENT_QUOTES, 'UTF-8') . "'
                                      data-localizacao='" . htmlspecialchars((string)$row['Localizacao'], ENT_QUOTES, 'UTF-8') . "'
                                      data-unidade-nome='" . htmlspecialchars((string)$localizacaoNome, ENT_QUOTES, 'UTF-8') . "'
                                      data-desc-localizacao='" . htmlspecialchars((string)$row['Descricao_Localizacao'], ENT_QUOTES, 'UTF-8') . "'
                                      data-status='" . htmlspecialchars((string)$row['Status'], ENT_QUOTES, 'UTF-8') . "'
                                      data-memorando='" . htmlspecialchars((string)$row['Memorando'], ENT_QUOTES, 'UTF-8') . "'
                                      data-marca='" . htmlspecialchars((string)($row['marca'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-modelo='" . htmlspecialchars((string)($row['modelo'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-numero-serie='" . htmlspecialchars((string)($row['numero_serie'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-cor='" . htmlspecialchars((string)($row['cor'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-ano-aquisicao='" . htmlspecialchars((string)($row['ano_aquisicao'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-nfe-numero='" . htmlspecialchars((string)($row['nfe_numero'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-fornecedor-nome='" . htmlspecialchars((string)($row['fornecedor_nome'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-fornecedor-cnpj='" . htmlspecialchars((string)($row['fornecedor_cnpj'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-valor-unitario='" . htmlspecialchars((string)($row['valor_unitario'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-valor-total-nota='" . htmlspecialchars((string)($row['valor_total_nota'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-em-uso='" . htmlspecialchars((string)($row['em_uso'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-estado-conservacao='" . htmlspecialchars((string)($row['estado_conservacao'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-origem-aquisicao='" . htmlspecialchars((string)($row['origem_aquisicao'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-nota-fiscal-anexo='" . htmlspecialchars((string)($row['nota_fiscal_anexo'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      onclick='abrirModalEditar(this)'>
                                      <i class='bi bi-pencil-fill'></i>
                                    </button>
                                    <button class='btn btn-danger btn-sm' title='Excluir'
                                      onclick=\"abrirModalExcluir(
                                        '{$row['N_Patrimonio']}',
                                        '$descricaoSemEnter',
                                        '{$row['Data_Entrada']}',
                                        '{$row['Localizacao']}',
                                        '$descricaoLocalSemEnter',
                                        '{$row['Status']}',
                                        '{$row['Memorando']}'
                                      )\">
                                      <i class='bi bi-trash-fill'></i>
                                    </button>
                                    <button class='btn btn-warning btn-sm' title='Descarte'
                                      onclick=\"abrirModalDescarte(
                                        '{$row['N_Patrimonio']}',
                                        '$descricaoSemEnter',
                                        '{$row['Data_Entrada']}',
                                        '{$row['Localizacao']}',
                                        '$descricaoLocalSemEnter',
                                        '{$row['Memorando']}'
                                      )\">
                                      <i class='bi bi-archive-fill'></i>
                                    </button>
                                  </td>";
                  } else {
                    echo "<td class='text-center'>
                                    <button class='btn btn-secondary btn-sm' title='Visualizar'
                                      type='button'
                                      data-numero-original='" . htmlspecialchars((string)$row['N_Patrimonio'], ENT_QUOTES, 'UTF-8') . "'
                                      data-numero-patrimonio='" . htmlspecialchars((string)$row['N_Patrimonio'], ENT_QUOTES, 'UTF-8') . "'
                                      data-numero-provisorio='" . (((int)($row['numero_provisorio'] ?? 0) === 1 || strpos((string)$row['N_Patrimonio'], 'PROV-') === 0) ? "1" : "0") . "'
                                      data-descricao='" . htmlspecialchars((string)$row['Descricao'], ENT_QUOTES, 'UTF-8') . "'
                                      data-data-entrada='" . htmlspecialchars((string)$row['Data_Entrada'], ENT_QUOTES, 'UTF-8') . "'
                                      data-localizacao='" . htmlspecialchars((string)$row['Localizacao'], ENT_QUOTES, 'UTF-8') . "'
                                      data-unidade-nome='" . htmlspecialchars((string)$localizacaoNome, ENT_QUOTES, 'UTF-8') . "'
                                      data-desc-localizacao='" . htmlspecialchars((string)$row['Descricao_Localizacao'], ENT_QUOTES, 'UTF-8') . "'
                                      data-status='" . htmlspecialchars((string)$row['Status'], ENT_QUOTES, 'UTF-8') . "'
                                      data-memorando='" . htmlspecialchars((string)$row['Memorando'], ENT_QUOTES, 'UTF-8') . "'
                                      data-marca='" . htmlspecialchars((string)($row['marca'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-modelo='" . htmlspecialchars((string)($row['modelo'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-numero-serie='" . htmlspecialchars((string)($row['numero_serie'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-cor='" . htmlspecialchars((string)($row['cor'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-ano-aquisicao='" . htmlspecialchars((string)($row['ano_aquisicao'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-nfe-numero='" . htmlspecialchars((string)($row['nfe_numero'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-fornecedor-nome='" . htmlspecialchars((string)($row['fornecedor_nome'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-fornecedor-cnpj='" . htmlspecialchars((string)($row['fornecedor_cnpj'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-valor-unitario='" . htmlspecialchars((string)($row['valor_unitario'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-valor-total-nota='" . htmlspecialchars((string)($row['valor_total_nota'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-em-uso='" . htmlspecialchars((string)($row['em_uso'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-estado-conservacao='" . htmlspecialchars((string)($row['estado_conservacao'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-origem-aquisicao='" . htmlspecialchars((string)($row['origem_aquisicao'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-nota-fiscal-anexo='" . htmlspecialchars((string)($row['nota_fiscal_anexo'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      onclick='abrirModalVisualizar(this)'>
                                      <i class='bi bi-eye-fill'></i>
                                    </button>
                                    <button class='btn btn-primary btn-sm' title='Editar'
                                      type='button'
                                      data-numero-original='" . htmlspecialchars((string)$row['N_Patrimonio'], ENT_QUOTES, 'UTF-8') . "'
                                      data-numero-patrimonio='" . htmlspecialchars((string)$row['N_Patrimonio'], ENT_QUOTES, 'UTF-8') . "'
                                      data-numero-provisorio='" . (((int)($row['numero_provisorio'] ?? 0) === 1 || strpos((string)$row['N_Patrimonio'], 'PROV-') === 0) ? "1" : "0") . "'
                                      data-descricao='" . htmlspecialchars((string)$row['Descricao'], ENT_QUOTES, 'UTF-8') . "'
                                      data-data-entrada='" . htmlspecialchars((string)$row['Data_Entrada'], ENT_QUOTES, 'UTF-8') . "'
                                      data-localizacao='" . htmlspecialchars((string)$row['Localizacao'], ENT_QUOTES, 'UTF-8') . "'
                                      data-unidade-nome='" . htmlspecialchars((string)$localizacaoNome, ENT_QUOTES, 'UTF-8') . "'
                                      data-desc-localizacao='" . htmlspecialchars((string)$row['Descricao_Localizacao'], ENT_QUOTES, 'UTF-8') . "'
                                      data-status='" . htmlspecialchars((string)$row['Status'], ENT_QUOTES, 'UTF-8') . "'
                                      data-memorando='" . htmlspecialchars((string)$row['Memorando'], ENT_QUOTES, 'UTF-8') . "'
                                      data-marca='" . htmlspecialchars((string)($row['marca'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-modelo='" . htmlspecialchars((string)($row['modelo'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-numero-serie='" . htmlspecialchars((string)($row['numero_serie'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-cor='" . htmlspecialchars((string)($row['cor'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-ano-aquisicao='" . htmlspecialchars((string)($row['ano_aquisicao'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-nfe-numero='" . htmlspecialchars((string)($row['nfe_numero'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-fornecedor-nome='" . htmlspecialchars((string)($row['fornecedor_nome'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-fornecedor-cnpj='" . htmlspecialchars((string)($row['fornecedor_cnpj'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-valor-unitario='" . htmlspecialchars((string)($row['valor_unitario'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-valor-total-nota='" . htmlspecialchars((string)($row['valor_total_nota'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-em-uso='" . htmlspecialchars((string)($row['em_uso'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-estado-conservacao='" . htmlspecialchars((string)($row['estado_conservacao'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-origem-aquisicao='" . htmlspecialchars((string)($row['origem_aquisicao'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      data-nota-fiscal-anexo='" . htmlspecialchars((string)($row['nota_fiscal_anexo'] ?? ''), ENT_QUOTES, 'UTF-8') . "'
                                      onclick='abrirModalEditar(this)'>
                                      <i class='bi bi-pencil-fill'></i>
                                    </button>
                                    <button class='btn btn-danger btn-sm' title='Excluir'
                                      onclick=\"abrirModalExcluir(
                                        '{$row['N_Patrimonio']}',
                                        '$descricaoSemEnter',
                                        '{$row['Data_Entrada']}',
                                        '{$row['Localizacao']}',
                                        '$descricaoLocalSemEnter',
                                        '{$row['Status']}',
                                        '{$row['Memorando']}'
                                      )\">
                                      <i class='bi bi-trash-fill'></i>
                                    </button>
                                    <button class='btn btn-secondary btn-sm' title='Patrimônio já descartado' disabled>
                                      <i class='bi bi-archive-fill'></i>
                                    </button>
                                  </td>";
                  }
                  echo "</tr>";
                }
              } else {
                echo "<tr><td colspan='8' class='text-center'>Nenhum patrimônio encontrado</td></tr>";
              }

            } catch (Exception $e) {
              echo "<tr><td colspan='8' class='text-center text-danger'>Erro ao exibir registros: " . $e->getMessage() . "</td></tr>";
            }
            ?>
          </tbody>
        </table>
        <nav>
          <?php
          echo '<ul class="pagination justify-content-center mt-3">
                  <li class="page-item ' . (($page <= 1) ? 'disabled' : '') . '">
                  <a class="page-link" href="?page=1&search=' . urlencode($search) . '&local_filtro=' . urlencode($local_filtro_value) . '" title="Primeira"><i class="bi bi-skip-backward-fill"></i></a>
                  </li>
                  <li class="page-item ' . (($page <= 1) ? 'disabled' : '') . '">
                  <a class="page-link" href="?page=' . ($page - 1) . '&search=' . urlencode($search) . '&local_filtro=' . urlencode($local_filtro_value) . '" title="Voltar"><i class="bi bi-chevron-left"></i></a>
                  </li>';

          $start_page = max(1, $page - 2);
          $end_page = min($total_pages, $page + 2);
          for ($i = $start_page; $i <= $end_page; $i++) {
            echo '<li class="page-item ' . (($i == $page) ? 'active' : '') . '">
                    <a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&local_filtro=' . urlencode($local_filtro_value) . '">' . $i . '</a>
                    </li>';
          }

          echo '<li class="page-item ' . (($page >= $total_pages) ? 'disabled' : '') . '">
                  <a class="page-link" href="?page=' . ($page + 1) . '&search=' . urlencode($search) . '&local_filtro=' . urlencode($local_filtro_value) . '" title="Avançar"><i class="bi bi-chevron-right"></i></a>
                  </li>
                  <li class="page-item ' . (($page >= $total_pages) ? 'disabled' : '') . '">
                  <a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&local_filtro=' . urlencode($local_filtro_value) . '" title="Última"><i class="bi bi-skip-forward-fill"></i></a>
                  </li>
                  </ul>';
          ?>
        </nav>
      </div>
    </div>

    <?php include './modals/modalCadastrar.php'; ?>

    <?php include './modals/modalEditar.php'; ?>

    <?php include './modals/modalVisualizar.php'; ?>

    <?php include './modals/modalExcluir.php'; ?>

    <?php include './modals/modalDescarte.php'; ?>

    <?php include './modals/modalGerarPdf.php'; ?>

  </div>
  <?php close_connection($conn); ?>

  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
    integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
    crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"
    integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy"
    crossorigin="anonymous"></script>
  <script src="./scripts.js"></script>
</body>

</html>
