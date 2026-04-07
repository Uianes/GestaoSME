<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/db_connection.php';

function pat_table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function pat_column_exists(mysqli $conn, string $table, string $column): bool
{
    $tableSafe = $conn->real_escape_string($table);
    $columnSafe = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function pat_run_query(mysqli $conn, string $sql, array $params = []): mysqli_result|bool
{
    try {
        if ($params === []) {
            return $conn->query($sql);
        }
        return mysqli_execute_query($conn, $sql, $params);
    } catch (Throwable $e) {
        return false;
    }
}

function pat_ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

if (!user_can_access_system('patrimonio')) {
    pat_ensure_session();
    echo 'Sem permissão de acesso.';
    exit;
}

function pat_is_sme_unit_name(string $name): bool
{
    $normalized = preg_replace('/[^A-Z]/', '', strtoupper($name));
    return $normalized === 'SME';
}

/**
 * Returns [bool $userIsSme, int[] $userUnidades]
 */
function pat_user_context($conn = null): array
{
    pat_ensure_session();

    $userIsSme = !empty($_SESSION['user_is_sme']);
    $userUnidades = $_SESSION['user_unidades'] ?? [];
    if (!is_array($userUnidades)) {
        $userUnidades = [];
    }
    $userUnidades = array_values(array_filter(array_map('intval', $userUnidades), function ($value) {
        return $value > 0;
    }));

    if (!$userIsSme && empty($userUnidades)) {
        $matricula = (int)($_SESSION['user']['matricula'] ?? 0);
        if ($matricula > 0) {
            $localConn = $conn ?: open_connection();
            $ownsConnection = $conn === null;

            $schemaOk = pat_table_exists($localConn, 'vinculo')
                && pat_table_exists($localConn, 'unidade')
                && pat_table_exists($localConn, 'orgaos')
                && pat_column_exists($localConn, 'vinculo', 'matricula')
                && pat_column_exists($localConn, 'vinculo', 'id_unidade')
                && pat_column_exists($localConn, 'vinculo', 'id_orgao')
                && pat_column_exists($localConn, 'unidade', 'id_unidade')
                && pat_column_exists($localConn, 'unidade', 'nome')
                && pat_column_exists($localConn, 'orgaos', 'id_orgao')
                && pat_column_exists($localConn, 'orgaos', 'nome_orgao');

            if (!$schemaOk) {
                if ($ownsConnection) {
                    close_connection($localConn);
                }
                return [$userIsSme, $userUnidades];
            }

            $sql = "SELECT DISTINCT vinculo.id_unidade, unidade.nome AS unidade_nome, orgaos.nome_orgao
                    FROM vinculo
                    INNER JOIN unidade ON vinculo.id_unidade = unidade.id_unidade
                    INNER JOIN orgaos ON vinculo.id_orgao = orgaos.id_orgao
                    WHERE vinculo.matricula = ?
                    ORDER BY unidade.nome ASC";
            $result = pat_run_query($localConn, $sql, [$matricula]);

            if (!$result instanceof mysqli_result) {
                if ($ownsConnection) {
                    close_connection($localConn);
                }
                return [$userIsSme, $userUnidades];
            }

            $userUnidades = [];
            $userUnidadesNomes = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $idUnidade = (int)$row['id_unidade'];
                $nomeUnidade = (string)$row['unidade_nome'];
                if ($idUnidade > 0) {
                    $userUnidades[] = $idUnidade;
                    $userUnidadesNomes[$idUnidade] = $nomeUnidade;
                }
                $nomeOrgao = (string)($row['nome_orgao'] ?? '');
                if (pat_is_sme_unit_name($nomeUnidade) || pat_is_sme_unit_name($nomeOrgao)) {
                    $userIsSme = true;
                }
            }

            $_SESSION['user_unidades'] = $userUnidades;
            $_SESSION['user_unidades_names'] = $userUnidadesNomes;
            $_SESSION['user_is_sme'] = $userIsSme;
            if (!isset($_SESSION['user_local']) && !empty($userUnidades)) {
                $_SESSION['user_local'] = $userUnidades[0];
                $_SESSION['user_local_name'] = $userUnidadesNomes[$userUnidades[0]] ?? null;
            }

            if ($ownsConnection) {
                close_connection($localConn);
            }
        }
    }

    return [$userIsSme, $userUnidades];
}
