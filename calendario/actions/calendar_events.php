<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../auth/permissions.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
if (!user_can_access_system('calendario')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sem permissão de acesso.']);
    exit;
}

$conn = db();
$userMatricula = (int)($_SESSION['user']['matricula'] ?? 0);

function calendar_read_input(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST;
}

function calendar_normalize_datetime(?string $value): ?string
{
    if (!$value) {
        return null;
    }
    try {
        $dt = new DateTime($value);
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

function calendar_is_admin(): bool
{
    return !empty($_SESSION['user']['adm']) && (int)$_SESSION['user']['adm'] === 1;
}

function calendar_get_sme_matriculas(mysqli $conn): array
{
    $sql = "
        SELECT DISTINCT v.matricula
        FROM vinculo v
        INNER JOIN unidade u ON v.id_unidade = u.id_unidade
        INNER JOIN orgaos o ON v.id_orgao = o.id_orgao
        WHERE UPPER(REPLACE(REPLACE(u.nome, '.', ''), ' ', '')) = 'SME'
           OR UPPER(REPLACE(REPLACE(o.nome_orgao, '.', ''), ' ', '')) = 'SME'
    ";
    $result = $conn->query($sql);
    $matriculas = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $matriculas[] = (int)$row['matricula'];
        }
    }
    return $matriculas;
}

function calendar_get_users_by_unidades(mysqli $conn, array $unidades): array
{
    if (empty($unidades)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($unidades), '?'));
    $types = str_repeat('i', count($unidades));
    $sql = "
        SELECT DISTINCT v.matricula
        FROM vinculo v
        INNER JOIN usuarios u ON v.matricula = u.matricula
        WHERE v.id_unidade IN ($placeholders)
          AND u.ativo = 1
    ";
    $stmt = $conn->prepare($sql);
    $params = array_merge([$types], $unidades);
    $refs = [];
    foreach ($params as $index => $value) {
        $refs[$index] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $result = $stmt->get_result();
    $matriculas = [];
    while ($row = $result->fetch_assoc()) {
        $matriculas[] = (int)$row['matricula'];
    }
    $stmt->close();
    return $matriculas;
}

function calendar_build_recipients(mysqli $conn, int $creator, array $selectedUsers, array $selectedUnidades): array
{
    $recipients = [];
    $recipients[$creator] = true;

    foreach ($selectedUsers as $matricula) {
        $matricula = (int)$matricula;
        if ($matricula > 0) {
            $recipients[$matricula] = true;
        }
    }

    foreach (calendar_get_users_by_unidades($conn, $selectedUnidades) as $matricula) {
        $recipients[(int)$matricula] = true;
    }

    foreach (calendar_get_sme_matriculas($conn) as $matricula) {
        $recipients[(int)$matricula] = true;
    }

    return array_keys($recipients);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $stmt = $conn->prepare('
        SELECT e.id, e.titulo, e.descricao, e.inicio, e.fim, e.all_day, e.local, e.criado_por
        FROM eventos e
        INNER JOIN evento_destinatarios d ON d.evento_id = e.id
        WHERE d.matricula = ?
        ORDER BY e.inicio
    ');
    $stmt->bind_param('i', $userMatricula);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            'id' => (int)$row['id'],
            'title' => (string)$row['titulo'],
            'start' => (string)$row['inicio'],
            'end' => $row['fim'] ? (string)$row['fim'] : null,
            'allDay' => (int)$row['all_day'] === 1,
            'extendedProps' => [
                'descricao' => (string)($row['descricao'] ?? ''),
                'local' => (string)($row['local'] ?? ''),
                'criado_por' => (int)$row['criado_por'],
            ]
        ];
    }
    $stmt->close();
    echo json_encode($events);
    exit;
}

$input = calendar_read_input();
$action = $input['action'] ?? null;

if ($action === 'create') {
    $titulo = trim((string)($input['titulo'] ?? ''));
    $descricao = trim((string)($input['descricao'] ?? ''));
    $local = trim((string)($input['local'] ?? ''));
    $inicio = calendar_normalize_datetime($input['inicio'] ?? null);
    $fim = calendar_normalize_datetime($input['fim'] ?? null);
    $allDay = !empty($input['allDay']) ? 1 : 0;

    $selectedUsers = $input['usuarios'] ?? [];
    $selectedUnidades = $input['unidades'] ?? [];
    if (!is_array($selectedUsers)) {
        $selectedUsers = [];
    }
    if (!is_array($selectedUnidades)) {
        $selectedUnidades = [];
    }

    if ($titulo === '' || !$inicio) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Informe título e data de início.']);
        exit;
    }

    $stmt = $conn->prepare('
        INSERT INTO eventos (titulo, descricao, inicio, fim, all_day, local, criado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->bind_param('ssssisi', $titulo, $descricao, $inicio, $fim, $allDay, $local, $userMatricula);
    $stmt->execute();
    $eventId = $stmt->insert_id;
    $stmt->close();

    $recipients = calendar_build_recipients($conn, $userMatricula, $selectedUsers, $selectedUnidades);

    $destStmt = $conn->prepare('INSERT INTO evento_destinatarios (evento_id, matricula) VALUES (?, ?)');
    foreach ($recipients as $matricula) {
        $destStmt->bind_param('ii', $eventId, $matricula);
        $destStmt->execute();
    }
    $destStmt->close();

    if (!empty($selectedUsers)) {
        $userStmt = $conn->prepare('INSERT INTO evento_usuarios (evento_id, matricula) VALUES (?, ?)');
        foreach ($selectedUsers as $userId) {
            $target = (int)$userId;
            if ($target > 0) {
                $userStmt->bind_param('ii', $eventId, $target);
                $userStmt->execute();
            }
        }
        $userStmt->close();
    }

    if (!empty($selectedUnidades)) {
        $unitStmt = $conn->prepare('INSERT INTO evento_unidades (evento_id, id_unidade) VALUES (?, ?)');
        foreach ($selectedUnidades as $unidadeId) {
            $unitId = (int)$unidadeId;
            if ($unitId > 0) {
                $unitStmt->bind_param('ii', $eventId, $unitId);
                $unitStmt->execute();
            }
        }
        $unitStmt->close();
    }

    $notifTitle = 'Novo evento: ' . $titulo;
    $notifStmt = $conn->prepare('INSERT INTO notificacoes (matricula, evento_id, titulo, tipo) VALUES (?, ?, ?, ?)');
    foreach ($recipients as $matricula) {
        if ($matricula === $userMatricula) {
            continue;
        }
        $type = 'evento';
        $notifStmt->bind_param('iiss', $matricula, $eventId, $notifTitle, $type);
        $notifStmt->execute();
    }
    $notifStmt->close();

    echo json_encode(['ok' => true, 'id' => $eventId]);
    exit;
}

if ($action === 'details') {
    $eventId = (int)($input['id'] ?? 0);
    if ($eventId <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Evento inválido.']);
        exit;
    }

    $stmt = $conn->prepare('SELECT criado_por FROM eventos WHERE id = ?');
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Evento não encontrado.']);
        exit;
    }

    $criador = (int)$row['criado_por'];
    if (!calendar_is_admin() && $criador !== $userMatricula) {
        $stmt = $conn->prepare('SELECT 1 FROM evento_destinatarios WHERE evento_id = ? AND matricula = ? LIMIT 1');
        $stmt->bind_param('ii', $eventId, $userMatricula);
        $stmt->execute();
        $result = $stmt->get_result();
        $isRecipient = (bool)$result->fetch_assoc();
        $stmt->close();
        if (!$isRecipient) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Sem permissão para ver este evento.']);
            exit;
        }
    }

    $users = [];
    $result = $conn->query('SELECT matricula FROM evento_usuarios WHERE evento_id = ' . (int)$eventId);
    while ($row = $result->fetch_assoc()) {
        $users[] = (int)$row['matricula'];
    }

    $units = [];
    $result = $conn->query('SELECT id_unidade FROM evento_unidades WHERE evento_id = ' . (int)$eventId);
    while ($row = $result->fetch_assoc()) {
        $units[] = (int)$row['id_unidade'];
    }

    echo json_encode(['ok' => true, 'usuarios' => $users, 'unidades' => $units]);
    exit;
}

if ($action === 'update') {
    $eventId = (int)($input['id'] ?? 0);
    if ($eventId <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Evento inválido.']);
        exit;
    }

    $stmt = $conn->prepare('SELECT criado_por FROM eventos WHERE id = ?');
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Evento não encontrado.']);
        exit;
    }

    $criador = (int)$row['criado_por'];
    if (!calendar_is_admin() && $criador !== $userMatricula) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sem permissão para editar este evento.']);
        exit;
    }

    $titulo = trim((string)($input['titulo'] ?? ''));
    $descricao = trim((string)($input['descricao'] ?? ''));
    $local = trim((string)($input['local'] ?? ''));
    $inicio = calendar_normalize_datetime($input['inicio'] ?? null);
    $fim = calendar_normalize_datetime($input['fim'] ?? null);
    $allDay = !empty($input['allDay']) ? 1 : 0;

    if ($titulo === '' || !$inicio) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Informe título e data de início.']);
        exit;
    }

    $stmt = $conn->prepare('
        UPDATE eventos
        SET titulo = ?, descricao = ?, inicio = ?, fim = ?, all_day = ?, local = ?
        WHERE id = ?
    ');
    $stmt->bind_param('ssssisi', $titulo, $descricao, $inicio, $fim, $allDay, $local, $eventId);
    $stmt->execute();
    $stmt->close();

    $selectedUsers = $input['usuarios'] ?? [];
    $selectedUnidades = $input['unidades'] ?? [];
    if (!is_array($selectedUsers)) {
        $selectedUsers = [];
    }
    if (!is_array($selectedUnidades)) {
        $selectedUnidades = [];
    }

    $recipients = calendar_build_recipients($conn, $criador, $selectedUsers, $selectedUnidades);

    $conn->query('DELETE FROM evento_destinatarios WHERE evento_id = ' . (int)$eventId);
    $destStmt = $conn->prepare('INSERT INTO evento_destinatarios (evento_id, matricula) VALUES (?, ?)');
    foreach ($recipients as $matricula) {
        $destStmt->bind_param('ii', $eventId, $matricula);
        $destStmt->execute();
    }
    $destStmt->close();

    $conn->query('DELETE FROM evento_usuarios WHERE evento_id = ' . (int)$eventId);
    if (!empty($selectedUsers)) {
        $userStmt = $conn->prepare('INSERT INTO evento_usuarios (evento_id, matricula) VALUES (?, ?)');
        foreach ($selectedUsers as $userId) {
            $target = (int)$userId;
            if ($target > 0) {
                $userStmt->bind_param('ii', $eventId, $target);
                $userStmt->execute();
            }
        }
        $userStmt->close();
    }

    $conn->query('DELETE FROM evento_unidades WHERE evento_id = ' . (int)$eventId);
    if (!empty($selectedUnidades)) {
        $unitStmt = $conn->prepare('INSERT INTO evento_unidades (evento_id, id_unidade) VALUES (?, ?)');
        foreach ($selectedUnidades as $unidadeId) {
            $unitId = (int)$unidadeId;
            if ($unitId > 0) {
                $unitStmt->bind_param('ii', $eventId, $unitId);
                $unitStmt->execute();
            }
        }
        $unitStmt->close();
    }

    $notifTitle = 'Evento atualizado: ' . $titulo;
    $notifStmt = $conn->prepare('INSERT INTO notificacoes (matricula, evento_id, titulo, tipo) VALUES (?, ?, ?, ?)');
    foreach ($recipients as $matricula) {
        if ($matricula === $criador) {
            continue;
        }
        $type = 'evento';
        $notifStmt->bind_param('iiss', $matricula, $eventId, $notifTitle, $type);
        $notifStmt->execute();
    }
    $notifStmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'move') {
    $eventId = (int)($input['id'] ?? 0);
    $inicio = calendar_normalize_datetime($input['inicio'] ?? null);
    $fim = calendar_normalize_datetime($input['fim'] ?? null);
    $allDay = !empty($input['allDay']) ? 1 : 0;
    if ($eventId <= 0 || !$inicio) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Dados inválidos.']);
        exit;
    }

    $stmt = $conn->prepare('SELECT criado_por FROM eventos WHERE id = ?');
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Evento não encontrado.']);
        exit;
    }

    $criador = (int)$row['criado_por'];
    if (!calendar_is_admin() && $criador !== $userMatricula) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sem permissão para editar este evento.']);
        exit;
    }

    $stmt = $conn->prepare('UPDATE eventos SET inicio = ?, fim = ?, all_day = ? WHERE id = ?');
    $stmt->bind_param('ssii', $inicio, $fim, $allDay, $eventId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    $eventId = (int)($input['id'] ?? 0);
    if ($eventId <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Evento inválido.']);
        exit;
    }

    $stmt = $conn->prepare('SELECT criado_por FROM eventos WHERE id = ?');
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Evento não encontrado.']);
        exit;
    }

    $criador = (int)$row['criado_por'];
    if (!calendar_is_admin() && $criador !== $userMatricula) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sem permissão para excluir este evento.']);
        exit;
    }

    $conn->query('DELETE FROM notificacoes WHERE evento_id = ' . (int)$eventId);
    $conn->query('DELETE FROM evento_destinatarios WHERE evento_id = ' . (int)$eventId);
    $conn->query('DELETE FROM evento_usuarios WHERE evento_id = ' . (int)$eventId);
    $conn->query('DELETE FROM evento_unidades WHERE evento_id = ' . (int)$eventId);
    $conn->query('DELETE FROM eventos WHERE id = ' . (int)$eventId);

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Ação inválida.']);
