<?php

declare(strict_types=1);

require __DIR__ . '/auth_guard.php';
require __DIR__ . '/config.php';

compras_require_access();

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);

$payload = json_decode((string) ($_POST['selection'] ?? '[]'), true);
if (!is_array($payload)) {
    $payload = [];
}

$ids = [];
foreach ($payload as $entry) {
    if (!is_array($entry)) {
        continue;
    }
    $ids[] = (int) ($entry['item_id'] ?? 0);
    foreach (($entry['related_ids'] ?? []) as $relatedId) {
        $ids[] = (int) $relatedId;
    }
}

$ids = array_values(array_unique(array_filter($ids)));
$rows = fetch_export_items($ids);

$filename = 'pesquisa-precos-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'ID Item',
    'Descricao',
    'Categoria',
    'Subcategoria',
    'Orgao',
    'Modalidade',
    'Numero Licitacao',
    'Ano Licitacao',
    'Data Abertura',
    'Data Homologacao',
    'Quantidade',
    'Unidade',
    'Valor Unitario Estimado',
    'Valor Total Estimado',
    'Valor Unitario Homologado',
    'Valor Total Homologado',
    'Objeto Licitacao',
    'Link Licitacao',
], ';', '"', '\\');

foreach ($rows as $row) {
    fputcsv($out, [
        $row['id'] ?? '',
        $row['DS_ITEM'] ?? '',
        $row['categoria'] ?? '',
        $row['subcategoria'] ?? '',
        $row['NM_ORGAO'] ?? '',
        $row['CD_TIPO_MODALIDADE'] ?? '',
        $row['NR_LICITACAO'] ?? '',
        $row['ANO_LICITACAO'] ?? '',
        $row['DT_ABERTURA'] ?? '',
        $row['DT_HOMOLOGACAO'] ?? '',
        $row['QT_ITENS'] ?? '',
        $row['SG_UNIDADE_MEDIDA'] ?? '',
        money_value($row['VL_UNITARIO_ESTIMADO'] ?? null),
        money_value($row['VL_TOTAL_ESTIMADO'] ?? null),
        money_value($row['VL_UNITARIO_HOMOLOGADO'] ?? null),
        money_value($row['VL_TOTAL_HOMOLOGADO'] ?? null),
        $row['DS_OBJETO'] ?? '',
        $row['LINK_LICITACON_CIDADAO'] ?? '',
    ], ';', '"', '\\');
}

fclose($out);
exit;

function fetch_export_items(array $ids): array
{
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = compras_db()->prepare(
        "SELECT
          i.id,
          i.DS_ITEM,
          i.SG_UNIDADE_MEDIDA,
          i.QT_ITENS,
          i.VL_UNITARIO_ESTIMADO,
          i.VL_TOTAL_ESTIMADO,
          i.VL_UNITARIO_HOMOLOGADO,
          i.VL_TOTAL_HOMOLOGADO,
          cd.categoria,
          cd.subcategoria,
          l.NM_ORGAO,
          l.NR_LICITACAO,
          l.ANO_LICITACAO,
          l.CD_TIPO_MODALIDADE,
          l.DT_ABERTURA,
          l.DT_HOMOLOGACAO,
          l.LINK_LICITACON_CIDADAO,
          l.DS_OBJETO
        FROM item i
        LEFT JOIN item_categoria_ia ci ON ci.item_id = i.id
        LEFT JOIN item_categoria_descricao cd ON cd.descricao_hash = ci.descricao_hash
        LEFT JOIN licitacao l
          ON l.CD_ORGAO = i.CD_ORGAO
         AND l.NR_LICITACAO = i.NR_LICITACAO
         AND l.ANO_LICITACAO = i.ANO_LICITACAO
         AND l.CD_TIPO_MODALIDADE = i.CD_TIPO_MODALIDADE
        WHERE i.id IN ({$placeholders})
        ORDER BY FIELD(i.id, {$placeholders})"
    );
    $stmt->execute([...$ids, ...$ids]);

    return $stmt->fetchAll();
}
