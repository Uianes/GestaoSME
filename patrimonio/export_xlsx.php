<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db_connection.php';

function pat_export_bool_label($value): string
{
  if ($value === null || $value === '') {
    return '';
  }
  return (string)$value === '1' ? 'Sim' : 'Não';
}

function pat_export_xml(string $value): string
{
  return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function pat_export_col_name(int $index): string
{
  $name = '';
  while ($index >= 0) {
    $name = chr(($index % 26) + 65) . $name;
    $index = intdiv($index, 26) - 1;
  }
  return $name;
}

function pat_export_cell(int $rowIndex, int $columnIndex, string $value): string
{
  $ref = pat_export_col_name($columnIndex) . $rowIndex;
  $safeValue = pat_export_xml($value);
  return '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $safeValue . '</t></is></c>';
}

function pat_export_sheet_xml(array $headers, array $rows): string
{
  $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

  $rowIndex = 1;
  $xml .= '<row r="' . $rowIndex . '">';
  foreach ($headers as $columnIndex => $header) {
    $xml .= pat_export_cell($rowIndex, $columnIndex, (string)$header);
  }
  $xml .= '</row>';

  foreach ($rows as $row) {
    $rowIndex++;
    $xml .= '<row r="' . $rowIndex . '">';
    foreach ($headers as $columnIndex => $header) {
      $xml .= pat_export_cell($rowIndex, $columnIndex, (string)($row[$header] ?? ''));
    }
    $xml .= '</row>';
  }

  $xml .= '</sheetData></worksheet>';
  return $xml;
}

function pat_export_base_url(): string
{
  $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/patrimonio/export_xlsx.php';
  $basePath = dirname(dirname($scriptName));
  if ($basePath === DIRECTORY_SEPARATOR || $basePath === '\\' || $basePath === '.') {
    $basePath = '';
  }
  return $scheme . '://' . $host . rtrim($basePath, '/');
}

function pat_export_send_xlsx(string $filename, string $sheet1Xml, string $sheet2Xml): void
{
  $tmpFile = tempnam(sys_get_temp_dir(), 'pat_xlsx_');
  if ($tmpFile === false) {
    throw new RuntimeException('Não foi possível preparar o arquivo temporário para exportação.');
  }

  $zip = new ZipArchive();
  if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmpFile);
    throw new RuntimeException('Não foi possível gerar o arquivo XLSX.');
  }

  $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
    . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
    . '</Types>';

  $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
    . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
    . '</Relationships>';

  $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets>'
    . '<sheet name="Patrimonios" sheetId="1" r:id="rId1"/>'
    . '<sheet name="Anexos" sheetId="2" r:id="rId2"/>'
    . '</sheets>'
    . '</workbook>';

  $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
    . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

  $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
    . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
    . '<borders count="1"><border/></borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
    . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
    . '</styleSheet>';

  $now = gmdate('Y-m-d\TH:i:s\Z');
  $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
    . '<dc:title>Exportação de Patrimônio</dc:title>'
    . '<dc:creator>Gestão SME</dc:creator>'
    . '<cp:lastModifiedBy>Gestão SME</cp:lastModifiedBy>'
    . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
    . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
    . '</cp:coreProperties>';

  $app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
    . '<Application>Gestão SME</Application>'
    . '</Properties>';

  $zip->addFromString('[Content_Types].xml', $contentTypes);
  $zip->addFromString('_rels/.rels', $rels);
  $zip->addFromString('xl/workbook.xml', $workbook);
  $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
  $zip->addFromString('xl/styles.xml', $styles);
  $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1Xml);
  $zip->addFromString('xl/worksheets/sheet2.xml', $sheet2Xml);
  $zip->addFromString('docProps/core.xml', $core);
  $zip->addFromString('docProps/app.xml', $app);
  $zip->close();

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . filesize($tmpFile));
  header('Cache-Control: max-age=0');
  readfile($tmpFile);
  @unlink($tmpFile);
}

try {
  $conn = open_connection();
  [$userIsSme, $userUnidades] = pat_user_context($conn);

  $whereConditions = [];
  if (!$userIsSme) {
    if (count($userUnidades) > 0) {
      $whereConditions[] = 'p.Localizacao IN (' . implode(',', array_map('intval', $userUnidades)) . ')';
    } else {
      $whereConditions[] = '1 = 0';
    }
  }
  $whereSql = count($whereConditions) > 0 ? ' WHERE ' . implode(' AND ', $whereConditions) : '';

  $hasUsers = function_exists('pat_table_exists') && pat_table_exists($conn, 'usuarios') && pat_column_exists($conn, 'usuarios', 'matricula') && pat_column_exists($conn, 'usuarios', 'nome');
  $hasCadastroUser = pat_column_exists($conn, 'patrimonio', 'cadastrado_por');
  $hasCadastroUnit = pat_column_exists($conn, 'patrimonio', 'unidade_vinculada_cadastro');

  $creatorJoin = ($hasUsers && $hasCadastroUser) ? ' LEFT JOIN usuarios uc ON uc.matricula = p.cadastrado_por ' : '';
  $cadUnitJoin = $hasCadastroUnit ? ' LEFT JOIN unidade uv ON uv.id_unidade = p.unidade_vinculada_cadastro ' : '';
  $creatorSelect = ($hasUsers && $hasCadastroUser) ? ', uc.nome AS cadastrado_por_nome ' : ', NULL AS cadastrado_por_nome ';
  $cadUnitSelect = $hasCadastroUnit ? ', uv.nome AS unidade_vinculada_cadastro_nome ' : ', NULL AS unidade_vinculada_cadastro_nome ';

  $sql = "SELECT p.*, u.nome AS unidade_nome{$creatorSelect}{$cadUnitSelect}
          FROM patrimonio p
          LEFT JOIN unidade u ON u.id_unidade = p.Localizacao
          {$creatorJoin}
          {$cadUnitJoin}
          {$whereSql}
          ORDER BY p.Data_Entrada DESC, p.N_Patrimonio ASC";

  $result = mysqli_query($conn, $sql);
  if (!$result) {
    throw new RuntimeException('Não foi possível consultar os patrimônios: ' . mysqli_error($conn));
  }

  $baseUrl = pat_export_base_url();
  $rows = [];
  $attachmentRows = [];

  while ($row = mysqli_fetch_assoc($result)) {
    $attachmentPath = (string)($row['nota_fiscal_anexo'] ?? '');
    $attachmentUrl = $attachmentPath !== '' ? $baseUrl . '/' . ltrim($attachmentPath, '/') : '';
    $isProvisional = ((int)($row['numero_provisorio'] ?? 0) === 1 || strpos((string)($row['N_Patrimonio'] ?? ''), 'PROV-') === 0) ? 'Sim' : 'Não';

    $rows[] = [
      'Nº Patrimônio' => (string)($row['N_Patrimonio'] ?? ''),
      'Número provisório' => $isProvisional,
      'Descrição' => (string)($row['Descricao'] ?? ''),
      'Marca' => (string)($row['marca'] ?? ''),
      'Modelo' => (string)($row['modelo'] ?? ''),
      'Número de série' => (string)($row['numero_serie'] ?? ''),
      'Cor' => (string)($row['cor'] ?? ''),
      'Data de entrada' => (string)($row['Data_Entrada'] ?? ''),
      'Ano da aquisição' => (string)($row['ano_aquisicao'] ?? ''),
      'Origem da aquisição' => (string)($row['origem_aquisicao'] ?? ''),
      'NFC-e / Nota fiscal' => (string)($row['nfe_numero'] ?? ''),
      'Fornecedor' => (string)($row['fornecedor_nome'] ?? ''),
      'CNPJ do fornecedor' => (string)($row['fornecedor_cnpj'] ?? ''),
      'Valor unitário' => (string)($row['valor_unitario'] ?? ''),
      'Valor total da nota' => (string)($row['valor_total_nota'] ?? ''),
      'Localização ID' => (string)($row['Localizacao'] ?? ''),
      'Localização nome' => (string)($row['unidade_nome'] ?? ''),
      'Descrição da localização' => (string)($row['Descricao_Localizacao'] ?? ''),
      'Está em uso?' => pat_export_bool_label($row['em_uso'] ?? null),
      'Estado de conservação' => (string)($row['estado_conservacao'] ?? ''),
      'Status' => (string)($row['Status'] ?? ''),
      'Memorando' => (string)($row['Memorando'] ?? ''),
      'Responsável pelo cadastro matrícula' => (string)($row['cadastrado_por'] ?? ''),
      'Responsável pelo cadastro nome' => (string)($row['cadastrado_por_nome'] ?? ''),
      'Data/hora do cadastro' => (string)($row['cadastrado_em'] ?? ''),
      'Unidade vinculada no cadastro ID' => (string)($row['unidade_vinculada_cadastro'] ?? ''),
      'Unidade vinculada no cadastro nome' => (string)($row['unidade_vinculada_cadastro_nome'] ?? ''),
      'Anexo da nota fiscal (caminho)' => $attachmentPath,
      'Anexo da nota fiscal (URL)' => $attachmentUrl,
    ];

    if ($attachmentPath !== '') {
      $attachmentRows[] = [
        'Nº Patrimônio' => (string)($row['N_Patrimonio'] ?? ''),
        'Descrição' => (string)($row['Descricao'] ?? ''),
        'Arquivo anexo' => basename($attachmentPath),
        'Caminho do anexo' => $attachmentPath,
        'URL do anexo' => $attachmentUrl,
      ];
    }
  }

  $mainHeaders = [
    'Nº Patrimônio',
    'Número provisório',
    'Descrição',
    'Marca',
    'Modelo',
    'Número de série',
    'Cor',
    'Data de entrada',
    'Ano da aquisição',
    'Origem da aquisição',
    'NFC-e / Nota fiscal',
    'Fornecedor',
    'CNPJ do fornecedor',
    'Valor unitário',
    'Valor total da nota',
    'Localização ID',
    'Localização nome',
    'Descrição da localização',
    'Está em uso?',
    'Estado de conservação',
    'Status',
    'Memorando',
    'Responsável pelo cadastro matrícula',
    'Responsável pelo cadastro nome',
    'Data/hora do cadastro',
    'Unidade vinculada no cadastro ID',
    'Unidade vinculada no cadastro nome',
    'Anexo da nota fiscal (caminho)',
    'Anexo da nota fiscal (URL)',
  ];
  $attachmentHeaders = [
    'Nº Patrimônio',
    'Descrição',
    'Arquivo anexo',
    'Caminho do anexo',
    'URL do anexo',
  ];

  $sheet1Xml = pat_export_sheet_xml($mainHeaders, $rows);
  $sheet2Xml = pat_export_sheet_xml($attachmentHeaders, $attachmentRows);
  $filename = 'patrimonios_' . date('Ymd_His') . '.xlsx';

  close_connection($conn);
  pat_export_send_xlsx($filename, $sheet1Xml, $sheet2Xml);
  exit;
} catch (Throwable $e) {
  if (isset($conn) && $conn instanceof mysqli) {
    close_connection($conn);
  }
  http_response_code(500);
  header('Content-Type: text/plain; charset=UTF-8');
  echo 'Erro ao exportar patrimônios: ' . $e->getMessage();
}
