<?php

if (!function_exists('proto_group_table_exists')) {
    function proto_group_table_exists(mysqli $conn, string $table): bool
    {
        $safe = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('proto_group_column_exists')) {
    function proto_group_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $tableSafe = $conn->real_escape_string($table);
        $columnSafe = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('proto_groups_schema_ready')) {
    function proto_groups_schema_ready(mysqli $conn): bool
    {
        return proto_group_table_exists($conn, 'doc_grupos')
            && proto_group_table_exists($conn, 'doc_grupo_usuarios')
            && proto_group_table_exists($conn, 'doc_documento_grupos')
            && proto_group_column_exists($conn, 'doc_grupos', 'id')
            && proto_group_column_exists($conn, 'doc_grupos', 'nome')
            && proto_group_column_exists($conn, 'doc_grupos', 'descricao')
            && proto_group_column_exists($conn, 'doc_grupos', 'ativo')
            && proto_group_column_exists($conn, 'doc_grupo_usuarios', 'grupo_id')
            && proto_group_column_exists($conn, 'doc_grupo_usuarios', 'usuario_matricula')
            && proto_group_column_exists($conn, 'doc_documento_grupos', 'documento_id')
            && proto_group_column_exists($conn, 'doc_documento_grupos', 'grupo_id');
    }
}

if (!function_exists('proto_groups_schema_sql')) {
    function proto_groups_schema_sql(): string
    {
        return "CREATE TABLE doc_grupos (\n"
            . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
            . "  nome VARCHAR(120) NOT NULL,\n"
            . "  descricao VARCHAR(255) NULL,\n"
            . "  ativo TINYINT(1) NOT NULL DEFAULT 1,\n"
            . "  criado_por INT NULL,\n"
            . "  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n"
            . ");\n\n"
            . "CREATE TABLE doc_grupo_usuarios (\n"
            . "  grupo_id INT NOT NULL,\n"
            . "  usuario_matricula INT NOT NULL,\n"
            . "  PRIMARY KEY (grupo_id, usuario_matricula)\n"
            . ");\n\n"
            . "CREATE TABLE doc_documento_grupos (\n"
            . "  documento_id INT NOT NULL,\n"
            . "  grupo_id INT NOT NULL,\n"
            . "  PRIMARY KEY (documento_id, grupo_id)\n"
            . ");";
    }
}

if (!function_exists('proto_fetch_recipient_groups')) {
    function proto_fetch_recipient_groups(mysqli $conn): array
    {
        if (!proto_groups_schema_ready($conn)) {
            return [];
        }

        $groups = [];
        $sql = '
            SELECT g.id, g.nome, g.descricao, g.ativo, COUNT(gu.usuario_matricula) AS total_usuarios
            FROM doc_grupos g
            LEFT JOIN doc_grupo_usuarios gu ON gu.grupo_id = g.id
            WHERE g.ativo = 1
            GROUP BY g.id, g.nome, g.descricao, g.ativo
            ORDER BY g.nome
        ';
        $result = $conn->query($sql);
        if (!$result instanceof mysqli_result) {
            return [];
        }
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }
        return $groups;
    }
}

if (!function_exists('proto_fetch_document_groups')) {
    function proto_fetch_document_groups(mysqli $conn, int $documentoId): array
    {
        if ($documentoId <= 0 || !proto_groups_schema_ready($conn)) {
            return [];
        }

        $groups = [];
        $stmt = $conn->prepare('
            SELECT g.id, g.nome, g.descricao
            FROM doc_documento_grupos dg
            INNER JOIN doc_grupos g ON g.id = dg.grupo_id
            WHERE dg.documento_id = ?
            ORDER BY g.nome
        ');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $documentoId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }
        $stmt->close();
        return $groups;
    }
}

if (!function_exists('proto_expand_group_user_ids')) {
    function proto_expand_group_user_ids(mysqli $conn, array $groupIds): array
    {
        if (!proto_groups_schema_ready($conn)) {
            return [];
        }

        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), static fn($id) => $id > 0)));
        if (empty($groupIds)) {
            return [];
        }

        $sql = 'SELECT DISTINCT usuario_matricula FROM doc_grupo_usuarios WHERE grupo_id IN (' . implode(',', $groupIds) . ') ORDER BY usuario_matricula';
        $result = $conn->query($sql);
        if (!$result instanceof mysqli_result) {
            return [];
        }

        $userIds = [];
        while ($row = $result->fetch_assoc()) {
            $userId = (int)($row['usuario_matricula'] ?? 0);
            if ($userId > 0) {
                $userIds[] = $userId;
            }
        }
        return array_values(array_unique($userIds));
    }
}
