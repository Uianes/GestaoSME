<?php

declare(strict_types=1);

require __DIR__ . '/auth_guard.php';
require __DIR__ . '/config.php';
require __DIR__ . '/PdfReport.php';

compras_require_access();

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

$items = fetch_report_items($ids);
$pdf = new PdfReport();
$rows = [];
foreach ($items as $item) {
    $rows[] = [
        'id' => (string) ($item['id'] ?? ''),
        'categoria' => (string) ($item['categoria'] ?? 'Não classificado'),
        'subcategoria' => (string) ($item['subcategoria'] ?? ''),
        'orgao' => (string) ($item['NM_ORGAO'] ?? ''),
        'modalidade' => (string) ($item['CD_TIPO_MODALIDADE'] ?? ''),
        'licitacao' => (string) ($item['NR_LICITACAO'] ?? ''),
        'ano' => (string) ($item['ANO_LICITACAO'] ?? ''),
        'abertura' => (string) ($item['DT_ABERTURA'] ?? ''),
        'homologacao' => (string) ($item['DT_HOMOLOGACAO'] ?? ''),
        'quantidade' => (string) ($item['QT_ITENS'] ?? ''),
        'unidade' => (string) ($item['SG_UNIDADE_MEDIDA'] ?? ''),
        'valor_unitario' => format_money(
            money_value($item['VL_UNITARIO_HOMOLOGADO'] ?? null)
            ?? money_value($item['VL_UNITARIO_ESTIMADO'] ?? null)
        ),
        'valor_total' => format_money(
            money_value($item['VL_TOTAL_HOMOLOGADO'] ?? null)
            ?? money_value($item['VL_TOTAL_ESTIMADO'] ?? null)
        ),
        'link' => (string) ($item['LINK_LICITACON_CIDADAO'] ?? ''),
    ];
}

$pdf->addTable($rows);

$pdf->output('pesquisa-precos-' . date('Ymd-His') . '.pdf');

function fetch_report_items(array $ids): array
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
