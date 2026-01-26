<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db_connection.php';

function pat_ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name(SESSION_NAME);
        session_start();
    }
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
            $sql = "SELECT DISTINCT vinculo.id_unidade, unidade.nome AS unidade_nome, orgaos.nome_orgao
                    FROM vinculo
                    INNER JOIN unidade ON vinculo.id_unidade = unidade.id_unidade
                    INNER JOIN orgaos ON vinculo.id_orgao = orgaos.id_orgao
                    WHERE vinculo.matricula = ?
                    ORDER BY unidade.nome ASC";
            $result = mysqli_execute_query($localConn, $sql, [$matricula]);

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

            if ($conn === null) {
                close_connection($localConn);
            }
        }
    }

    return [$userIsSme, $userUnidades];
}
