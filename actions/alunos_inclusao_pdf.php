<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
@ini_set('display_errors', '0');
if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/permissions.php';

if (!is_logged_in()) {
    header('Location: ../app.php?page=login');
    exit;
}

$autoload = __DIR__ . '/../patrimonio/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Biblioteca de PDF não encontrada (patrimonio/vendor/autoload.php).';
    exit;
}

require_once $autoload;

use Dompdf\Dompdf;

try {
    $conn = db();
    $userMatricula = (int)($_SESSION['user']['matricula'] ?? 0);
    $canView = user_can_access_system('alunos_inclusao');

    if (!$canView) {
        http_response_code(403);
        echo 'Sem permissão para acessar este relatório.';
        exit;
    }

function h_pdf($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function normalize_sme_pdf(string $name): string
{
    return preg_replace('/[^A-Z]/', '', strtoupper($name));
}

function ph_list_pdf(int $count): string
{
    return implode(',', array_fill(0, $count, '?'));
}

function tipo_profissional_label_pdf(?string $tipo): string
{
    return match ((string)$tipo) {
        'monitor' => 'Monitor',
        'interprete_libras' => 'Intérprete de LIBRAS',
        'cuidador' => 'Cuidador',
        'outro' => 'Outro',
        default => 'Monitor',
    };
}

function cid_like_keys(string $cid): array
{
    $cid = strtoupper(trim($cid));
    if ($cid === '') {
        return ['Sem CID especifico'];
    }
    preg_match_all('/\b(?:[A-Z]\d{2}(?:\.[A-Z0-9]+)?|\d[A-Z]\d{2}(?:\.[A-Z0-9]+)?)\b/u', $cid, $m);
    $keys = array_values(array_unique(array_filter($m[0] ?? [])));
    return !empty($keys) ? $keys : ['Sem CID especifico'];
}

function inclusao_user_context_pdf(mysqli $conn, int $matricula): array
{
    if ($matricula <= 0) {
        return [false, []];
    }
    $sql = "
        SELECT DISTINCT v.id_unidade, u.nome AS unidade_nome, o.nome_orgao
        FROM vinculo v
        LEFT JOIN unidade u ON u.id_unidade = v.id_unidade
        LEFT JOIN orgaos o ON o.id_orgao = v.id_orgao
        WHERE v.matricula = ?
    ";
    $result = mysqli_execute_query($conn, $sql, [$matricula]);
    $isSme = false;
    $units = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $unitId = (int)($row['id_unidade'] ?? 0);
        if ($unitId > 0) {
            $units[] = $unitId;
        }
        $unitName = (string)($row['unidade_nome'] ?? '');
        $orgaoName = (string)($row['nome_orgao'] ?? '');
        if (normalize_sme_pdf($unitName) === 'SME' || normalize_sme_pdf($orgaoName) === 'SME') {
            $isSme = true;
        }
    }
    return [$isSme, array_values(array_unique($units))];
}

[$userIsSme, $userUnits] = inclusao_user_context_pdf($conn, $userMatricula);

$reportType = (string)($_GET['type'] ?? 'escola');
if (!in_array($reportType, ['escola', 'geral', 'cid_like'], true)) {
    $reportType = 'escola';
}
if ($reportType === 'geral' && !$userIsSme) {
    $reportType = 'escola';
}
$filterAluno = trim((string)($_GET['aluno'] ?? ''));
$filterCid = trim((string)($_GET['cid'] ?? ''));

$hasNomeMonitor = false;
$hasMatriculaMonitorTexto = false;
$hasMonitorMatricula = false;
$hasAcompanhanteMatricula = false;
$hasTipoAcompanhamento = false;
$hasParticipaAee = false;
$resCols = $conn->query("SHOW COLUMNS FROM alunos_aee");
if ($resCols) {
    while ($c = mysqli_fetch_assoc($resCols)) {
        $f = (string)($c['Field'] ?? '');
        if ($f === 'nome_monitor') {
            $hasNomeMonitor = true;
        } elseif ($f === 'matricula_monitor') {
            $hasMatriculaMonitorTexto = true;
        } elseif ($f === 'monitor_matricula') {
            $hasMonitorMatricula = true;
        } elseif ($f === 'acompanhante_matricula') {
            $hasAcompanhanteMatricula = true;
        } elseif ($f === 'tipo_acompanhamento') {
            $hasTipoAcompanhamento = true;
        } elseif ($f === 'participa_aee') {
            $hasParticipaAee = true;
        }
    }
}

$monitorColumn = $hasNomeMonitor
    ? 'nome_monitor'
    : ($hasMatriculaMonitorTexto ? 'matricula_monitor' : ($hasMonitorMatricula ? 'monitor_matricula' : null));
$professionalMatriculaColumn = $hasAcompanhanteMatricula
    ? 'acompanhante_matricula'
    : ($hasMonitorMatricula ? 'monitor_matricula' : null);

if ($professionalMatriculaColumn !== null) {
    $selectMonitor = ", um.nome AS monitor_nome";
    $joinMonitor = "LEFT JOIN usuarios um ON um.matricula = aa.{$professionalMatriculaColumn}";
    $selectTipoProfissional = $hasTipoAcompanhamento
        ? ", aa.tipo_acompanhamento AS tipo_profissional"
        : ", NULL AS tipo_profissional";
    $joinTipoProfissional = "";
} elseif ($monitorColumn !== null) {
    $selectMonitor = ", aa.`{$monitorColumn}` AS monitor_nome";
    $joinMonitor = "";
    $selectTipoProfissional = $hasTipoAcompanhamento
        ? ", aa.tipo_acompanhamento AS tipo_profissional"
        : ", NULL AS tipo_profissional";
    $joinTipoProfissional = "";
} else {
    $selectMonitor = ", NULL AS monitor_nome";
    $joinMonitor = "";
    $selectTipoProfissional = $hasTipoAcompanhamento
        ? ", aa.tipo_acompanhamento AS tipo_profissional"
        : ", NULL AS tipo_profissional";
    $joinTipoProfissional = "";
}

$params = [];
$whereSql = '';
if (!$userIsSme) {
    if (empty($userUnits)) {
        $whereSql = ' AND 1 = 0 ';
    } else {
        $whereSql = " AND t.id_escola IN (" . ph_list_pdf(count($userUnits)) . ") ";
        $params = $userUnits;
    }
}
if ($filterAluno !== '') {
    $whereSql .= " AND (COALESCE(a.nome, aa.nome_aluno) LIKE ? OR COALESCE(a.matricula, aa.matricula_usuario) LIKE ?) ";
    $params[] = '%' . $filterAluno . '%';
    $params[] = '%' . $filterAluno . '%';
}
if ($filterCid !== '') {
    $whereSql .= " AND aa.cid LIKE ? ";
    $params[] = '%' . $filterCid . '%';
}

$sql = "
    SELECT
        MAX(COALESCE(a.nome, aa.nome_aluno)) AS aluno_nome,
        MAX(COALESCE(a.matricula, aa.matricula_usuario)) AS matricula_aluno,
        COALESCE(NULLIF(GROUP_CONCAT(DISTINCT un.nome ORDER BY un.nome SEPARATOR ', '), ''), 'Sem escola') AS escola_nome,
        MAX(aa.cid) AS cid,
        MAX(aa.diagnostico_fechado) AS diagnostico_fechado,
        MAX(aa.suspeita) AS suspeita,
        MAX(aa.frequenta_teabraca) AS frequenta_teabraca," .
        ($hasParticipaAee ? "
        MAX(aa.participa_aee) AS participa_aee," : "
        0 AS participa_aee,") . "
        MAX(aa.monitor_exclusivo) AS monitor_exclusivo
        {$selectTipoProfissional}
        {$selectMonitor}
    FROM alunos_aee aa
    LEFT JOIN alunos a ON a.matricula = aa.matricula_usuario
    {$joinMonitor}
    {$joinTipoProfissional}
    LEFT JOIN turma_alunos ta ON ta.aluno_id = COALESCE(a.matricula, aa.matricula_usuario)
    LEFT JOIN turmas t ON t.id = ta.turma_id
    LEFT JOIN unidade un ON un.id_unidade = t.id_escola
    WHERE 1 = 1
    {$whereSql}
    GROUP BY aa.id
    ORDER BY escola_nome ASC, aluno_nome ASC
";

if ($monitorColumn !== null || $professionalMatriculaColumn !== null) {
    if ($professionalMatriculaColumn !== null) {
        $sql = str_replace(
            ", um.nome AS monitor_nome",
            ", MAX(um.nome) AS monitor_nome",
            $sql
        );
    } else {
        $sql = str_replace(
            ", aa.`{$monitorColumn}` AS monitor_nome",
            ", MAX(aa.`{$monitorColumn}`) AS monitor_nome",
            $sql
        );
    }
}

$res = empty($params) ? $conn->query($sql) : mysqli_execute_query($conn, $sql, $params);
$rows = [];
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
}

$title = $reportType === 'geral'
    ? 'Relatorio AEE - SME por escola'
    : ($reportType === 'cid_like' ? 'Relatorio AEE - Agrupado por CID (similar)' : 'Relatorio AEE - Minha escola');

$isWideReport = in_array($reportType, ['escola', 'geral'], true);
$paperOrientation = $isWideReport ? 'landscape' : 'portrait';

$html = '<!doctype html><html><head><meta charset="utf-8"><style>
@page{margin:20px 18px}
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#222}
h1{font-size:16px;margin:0 0 8px}
h2{font-size:12px;margin:16px 0 6px}
.meta{font-size:10px;color:#555;margin-bottom:10px}
table{width:100%;border-collapse:collapse;table-layout:fixed;margin-bottom:10px}
th,td{border:1px solid #dcdcdc;padding:4px 5px;vertical-align:top;word-wrap:break-word;overflow-wrap:anywhere}
th{background:#f3f3f3;text-align:left;font-size:9px}
td{font-size:10px}
.w-aluno{width:18%}
.w-matricula{width:10%}
.w-escola{width:16%}
.w-cid{width:14%}
.w-flag{width:7%}
.w-tipo{width:10%}
.w-profissional{width:18%}
</style></head><body>';

$html .= '<h1>' . h_pdf($title) . '</h1>';
$html .= '<div class="meta">Emitido em ' . date('d/m/Y H:i') . '</div>';
if ($filterAluno !== '' || $filterCid !== '') {
    $html .= '<div class="meta">Filtros: '
        . ($filterAluno !== '' ? 'Aluno/Matricula = "' . h_pdf($filterAluno) . '" ' : '')
        . ($filterCid !== '' ? 'CID contem "' . h_pdf($filterCid) . '"' : '')
        . '</div>';
}

if (empty($rows)) {
    $html .= '<p>Nenhum aluno encontrado para este filtro.</p>';
} elseif ($reportType === 'cid_like') {
    $byCid = [];
    foreach ($rows as $row) {
        $keys = cid_like_keys((string)($row['cid'] ?? ''));
        foreach ($keys as $k) {
            if (!isset($byCid[$k])) {
                $byCid[$k] = [];
            }
            $byCid[$k][] = $row;
        }
    }
    ksort($byCid);
    foreach ($byCid as $cidKey => $items) {
        $html .= '<h2>' . h_pdf($cidKey) . ' (' . count($items) . ')</h2>';
        $html .= '<table><thead><tr><th class="w-aluno">Aluno</th><th class="w-matricula">Matrícula</th><th class="w-escola">Escola</th><th class="w-cid">CID completo</th><th class="w-tipo">Tipo</th><th class="w-profissional">Profissional</th></tr></thead><tbody>';
        foreach ($items as $r) {
            $html .= '<tr>'
                . '<td>' . h_pdf($r['aluno_nome'] ?? '-') . '</td>'
                . '<td>' . h_pdf($r['matricula_aluno'] ?? '-') . '</td>'
                . '<td>' . h_pdf($r['escola_nome'] ?? '-') . '</td>'
                . '<td>' . h_pdf($r['cid'] ?: '-') . '</td>'
                . '<td>' . (((int)($r['monitor_exclusivo'] ?? 0) === 1) ? h_pdf(tipo_profissional_label_pdf($r['tipo_profissional'] ?? 'monitor')) : '-') . '</td>'
                . '<td>' . h_pdf(($r['monitor_nome'] ?? '') !== '' ? $r['monitor_nome'] : '-') . '</td>'
                . '</tr>';
        }
        $html .= '</tbody></table>';
    }
} elseif ($reportType === 'geral') {
    $bySchool = [];
    foreach ($rows as $row) {
        $school = (string)($row['escola_nome'] ?? 'Sem escola');
        if (!isset($bySchool[$school])) {
            $bySchool[$school] = [];
        }
        $bySchool[$school][] = $row;
    }

    foreach ($bySchool as $school => $items) {
        $html .= '<h2>' . h_pdf($school) . '</h2>';
        $html .= '<table><thead><tr><th class="w-aluno">Aluno</th><th class="w-matricula">Matrícula</th><th class="w-cid">CID</th><th class="w-flag">Diag.</th><th class="w-flag">Suspeita</th><th class="w-flag">TEAbraça</th><th class="w-flag">Atend. AEE</th><th class="w-tipo">Tipo</th><th class="w-profissional">Profissional</th></tr></thead><tbody>';
        foreach ($items as $r) {
            $html .= '<tr>'
                . '<td>' . h_pdf($r['aluno_nome'] ?? '-') . '</td>'
                . '<td>' . h_pdf($r['matricula_aluno'] ?? '-') . '</td>'
                . '<td>' . h_pdf($r['cid'] ?: '-') . '</td>'
                . '<td>' . ((int)$r['diagnostico_fechado'] === 1 ? 'Sim' : 'Não') . '</td>'
                . '<td>' . ((int)$r['suspeita'] === 1 ? 'Sim' : 'Não') . '</td>'
                . '<td>' . ((int)$r['frequenta_teabraca'] === 1 ? 'Sim' : 'Não') . '</td>'
                . '<td>' . ((int)($r['participa_aee'] ?? 0) === 1 ? 'Sim' : 'Não') . '</td>'
                . '<td>' . (((int)($r['monitor_exclusivo'] ?? 0) === 1) ? h_pdf(tipo_profissional_label_pdf($r['tipo_profissional'] ?? 'monitor')) : '-') . '</td>'
                . '<td>' . h_pdf(($r['monitor_nome'] ?? '') !== '' ? $r['monitor_nome'] : '-') . '</td>'
                . '</tr>';
        }
        $html .= '</tbody></table>';
    }
} else {
    $html .= '<table><thead><tr><th class="w-aluno">Aluno</th><th class="w-matricula">Matrícula</th><th class="w-escola">Escola</th><th class="w-cid">CID</th><th class="w-flag">Diag.</th><th class="w-flag">Suspeita</th><th class="w-flag">TEAbraça</th><th class="w-flag">Atend. AEE</th><th class="w-tipo">Tipo</th><th class="w-profissional">Profissional</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $html .= '<tr>'
            . '<td>' . h_pdf($r['aluno_nome'] ?? '-') . '</td>'
            . '<td>' . h_pdf($r['matricula_aluno'] ?? '-') . '</td>'
            . '<td>' . h_pdf($r['escola_nome'] ?? '-') . '</td>'
            . '<td>' . h_pdf($r['cid'] ?: '-') . '</td>'
            . '<td>' . ((int)$r['diagnostico_fechado'] === 1 ? 'Sim' : 'Não') . '</td>'
            . '<td>' . ((int)$r['suspeita'] === 1 ? 'Sim' : 'Não') . '</td>'
            . '<td>' . ((int)$r['frequenta_teabraca'] === 1 ? 'Sim' : 'Não') . '</td>'
            . '<td>' . ((int)($r['participa_aee'] ?? 0) === 1 ? 'Sim' : 'Não') . '</td>'
            . '<td>' . (((int)($r['monitor_exclusivo'] ?? 0) === 1) ? h_pdf(tipo_profissional_label_pdf($r['tipo_profissional'] ?? 'monitor')) : '-') . '</td>'
            . '<td>' . h_pdf(($r['monitor_nome'] ?? '') !== '' ? $r['monitor_nome'] : '-') . '</td>'
            . '</tr>';
    }
    $html .= '</tbody></table>';
}

$html .= '</body></html>';

$dompdf = new Dompdf(['isRemoteEnabled' => true]);
$dompdf->setPaper('A4', $paperOrientation);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();

$filename = $reportType === 'geral' ? 'relatorio_aee_sme.pdf' : 'relatorio_aee_escola.pdf';
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
echo $dompdf->output();
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Erro ao gerar PDF: ' . $e->getMessage();
}
