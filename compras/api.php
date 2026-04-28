<?php

declare(strict_types=1);

require __DIR__ . '/auth_guard.php';
require __DIR__ . '/config.php';

compras_require_access(true);

$action = $_GET['action'] ?? '';

try {
    if ($action === 'items') {
        items();
    }

    if ($action === 'related') {
        related();
    }

    if ($action === 'categories') {
        categories();
    }

    json_response(['error' => 'Ação inválida.'], 400);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}

function categories(): never
{
    $stmt = compras_db()->query(
        "SELECT categoria, subcategoria, COUNT(*) AS total
         FROM item_categoria_descricao
         WHERE categoria <> 'Nao Classificado'
         GROUP BY categoria, subcategoria
         ORDER BY categoria, subcategoria"
    );

    json_response(['data' => $stmt->fetchAll()]);
}

function items(): never
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $category = trim((string) ($_GET['category'] ?? ''));
    $subcategory = trim((string) ($_GET['subcategory'] ?? ''));
    $onlyWithRelated = (string) ($_GET['only_with_related'] ?? '') === '1';
    $priceFilter = trim((string) ($_GET['price_filter'] ?? 'all'));
    $sort = trim((string) ($_GET['sort'] ?? 'recent'));
    $limit = min(max((int) ($_GET['limit'] ?? 48), 1), 100);
    $page = max((int) ($_GET['page'] ?? 1), 1);
    $offset = ($page - 1) * $limit;

    $where = ["i.DS_ITEM IS NOT NULL", "i.DS_ITEM <> ''"];
    $params = [];

    if ($q !== '') {
        $terms = preg_split('/\s+/', mb_strtoupper($q, 'UTF-8')) ?: [];
        $termIndex = 0;
        foreach ($terms as $term) {
            $term = trim($term);
            if ($term === '') {
                continue;
            }

            $param = 'q' . $termIndex;
            $where[] = "UPPER(i.DS_ITEM) LIKE :{$param}";
            $params[$param] = '%' . $term . '%';
            $termIndex++;
        }
    }

    if ($category !== '') {
        $where[] = "cd.categoria = :category";
        $params['category'] = $category;
    }

    if ($subcategory !== '') {
        $where[] = "cd.subcategoria = :subcategory";
        $params['subcategory'] = $subcategory;
    }

    if ($onlyWithRelated) {
        $where[] = "EXISTS (SELECT 1 FROM item_relacionado rel_filter WHERE rel_filter.item_id = i.id)";
    }

    $hasPriceSql = "(
        NULLIF(TRIM(i.VL_TOTAL_HOMOLOGADO), '') IS NOT NULL
        OR NULLIF(TRIM(i.VL_TOTAL_ESTIMADO), '') IS NOT NULL
        OR NULLIF(TRIM(i.VL_UNITARIO_HOMOLOGADO), '') IS NOT NULL
        OR NULLIF(TRIM(i.VL_UNITARIO_ESTIMADO), '') IS NOT NULL
    )";

    if ($priceFilter === 'with') {
        $where[] = $hasPriceSql;
    } elseif ($priceFilter === 'without') {
        $where[] = "NOT {$hasPriceSql}";
    }

    $fromSql = "
        FROM item i
        LEFT JOIN item_categoria_ia ci ON ci.item_id = i.id
        LEFT JOIN item_categoria_descricao cd ON cd.descricao_hash = ci.descricao_hash
        LEFT JOIN licitacao l
          ON l.CD_ORGAO = i.CD_ORGAO
         AND l.NR_LICITACAO = i.NR_LICITACAO
         AND l.ANO_LICITACAO = i.ANO_LICITACAO
         AND l.CD_TIPO_MODALIDADE = i.CD_TIPO_MODALIDADE
    ";
    $whereSql = "WHERE " . implode(' AND ', $where);

    $countStmt = compras_db()->prepare("SELECT COUNT(*) {$fromSql} {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $orderSql = $sort === 'related'
        ? "ORDER BY related_count DESC, i.id DESC"
        : "ORDER BY i.id DESC";

    $sql = "
        SELECT
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
          (
            SELECT COUNT(*)
            FROM item_relacionado rel
            WHERE rel.item_id = i.id
          ) AS related_count
        {$fromSql}
        {$whereSql}
        {$orderSql}
        LIMIT {$limit} OFFSET {$offset}";

    $stmt = compras_db()->prepare($sql);
    $stmt->execute($params);

    json_response([
        'data' => array_map('serialize_item', $stmt->fetchAll()),
        'meta' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int) max(1, ceil($total / $limit)),
        ],
    ]);
}

function related(): never
{
    $itemId = (int) ($_GET['item_id'] ?? 0);
    if ($itemId <= 0) {
        json_response(['error' => 'item_id inválido.'], 400);
    }

    $stmt = compras_db()->prepare(
        "SELECT
          r.score,
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
          l.DT_HOMOLOGACAO
        FROM item_relacionado r
        JOIN item i ON i.id = r.item_relacionado_id
        LEFT JOIN item_categoria_ia ci ON ci.item_id = i.id
        LEFT JOIN item_categoria_descricao cd ON cd.descricao_hash = ci.descricao_hash
        LEFT JOIN licitacao l
          ON l.CD_ORGAO = i.CD_ORGAO
         AND l.NR_LICITACAO = i.NR_LICITACAO
         AND l.ANO_LICITACAO = i.ANO_LICITACAO
         AND l.CD_TIPO_MODALIDADE = i.CD_TIPO_MODALIDADE
        WHERE r.item_id = :item_id
        ORDER BY r.score DESC
        LIMIT 20"
    );
    $stmt->execute(['item_id' => $itemId]);

    json_response(['data' => array_map('serialize_item', $stmt->fetchAll())]);
}

function serialize_item(array $row): array
{
    $estimated = money_value($row['VL_TOTAL_ESTIMADO'] ?? null);
    $approved = money_value($row['VL_TOTAL_HOMOLOGADO'] ?? null);
    $unitEstimated = money_value($row['VL_UNITARIO_ESTIMADO'] ?? null);
    $unitApproved = money_value($row['VL_UNITARIO_HOMOLOGADO'] ?? null);

    return [
        'id' => (int) $row['id'],
        'description' => $row['DS_ITEM'] ?? '',
        'unit' => $row['SG_UNIDADE_MEDIDA'] ?? '',
        'quantity' => $row['QT_ITENS'] ?? '',
        'estimated_value' => $estimated,
        'approved_value' => $approved,
        'unit_estimated_value' => $unitEstimated,
        'unit_approved_value' => $unitApproved,
        'estimated_value_label' => format_money($estimated),
        'approved_value_label' => format_money($approved),
        'category' => $row['categoria'] ?? 'Não classificado',
        'subcategory' => $row['subcategoria'] ?? '',
        'agency' => $row['NM_ORGAO'] ?? '',
        'bid_number' => $row['NR_LICITACAO'] ?? '',
        'bid_year' => $row['ANO_LICITACAO'] ?? '',
        'modality' => $row['CD_TIPO_MODALIDADE'] ?? '',
        'opening_date' => $row['DT_ABERTURA'] ?? '',
        'homologation_date' => $row['DT_HOMOLOGACAO'] ?? '',
        'score' => isset($row['score']) ? (float) $row['score'] : null,
        'related_count' => isset($row['related_count']) ? (int) $row['related_count'] : 0,
    ];
}
