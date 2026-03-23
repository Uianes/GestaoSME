<?php
require_once __DIR__ . '/../config/db.php';

function planejamento_calendar_ensure_mapping_table(PDO $pdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS iniciativa_evento (
            id_iniciativa INT(11) NOT NULL,
            evento_id INT(11) NOT NULL,
            sincronizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_iniciativa),
            UNIQUE KEY uq_iniciativa_evento_evento (evento_id),
            CONSTRAINT iniciativa_evento_ibfk_1
                FOREIGN KEY (id_iniciativa) REFERENCES iniciativa (id_iniciativa) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $ensured = true;
}

function planejamento_calendar_lookup_event_id(PDO $pdo, int $idIniciativa): ?int
{
    planejamento_calendar_ensure_mapping_table($pdo);

    $stmt = $pdo->prepare('SELECT evento_id FROM iniciativa_evento WHERE id_iniciativa = ?');
    $stmt->execute([$idIniciativa]);
    $value = $stmt->fetchColumn();

    return $value !== false ? (int)$value : null;
}

function planejamento_calendar_store_mapping(PDO $pdo, int $idIniciativa, int $eventId): void
{
    planejamento_calendar_ensure_mapping_table($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO iniciativa_evento (id_iniciativa, evento_id)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE evento_id = VALUES(evento_id)'
    );
    $stmt->execute([$idIniciativa, $eventId]);
}

function planejamento_calendar_delete_mapping(PDO $pdo, int $idIniciativa): void
{
    planejamento_calendar_ensure_mapping_table($pdo);

    $stmt = $pdo->prepare('DELETE FROM iniciativa_evento WHERE id_iniciativa = ?');
    $stmt->execute([$idIniciativa]);
}

function planejamento_calendar_event_exists(mysqli $conn, int $eventId): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM eventos WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = (bool)$result->fetch_assoc();
    $stmt->close();

    return $exists;
}

function planejamento_calendar_trim_title(string $title, int $limit = 150): string
{
    $title = trim($title);
    if ($title === '') {
        return 'Planejamento PPA';
    }

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($title, 0, $limit, '...');
    }

    return strlen($title) > $limit ? substr($title, 0, $limit - 3) . '...' : $title;
}

function planejamento_calendar_recipients(mysqli $conn, int $creatorMatricula): array
{
    $recipients = [];
    if ($creatorMatricula > 0) {
        $recipients[$creatorMatricula] = true;
    }

    $sql = "
        SELECT DISTINCT v.matricula
        FROM vinculo v
        INNER JOIN unidade u ON v.id_unidade = u.id_unidade
        INNER JOIN orgaos o ON v.id_orgao = o.id_orgao
        INNER JOIN usuarios usr ON usr.matricula = v.matricula
        WHERE usr.ativo = 1
          AND (
                UPPER(REPLACE(REPLACE(u.nome, '.', ''), ' ', '')) = 'SME'
             OR UPPER(REPLACE(REPLACE(o.nome_orgao, '.', ''), ' ', '')) = 'SME'
          )
    ";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $recipients[(int)$row['matricula']] = true;
    }

    return array_keys($recipients);
}

function planejamento_calendar_build_payload(array $context): array
{
    $date = new DateTimeImmutable((string)$context['Quando']);
    $inicio = $date->format('Y-m-d 00:00:00');

    $titleBase = trim((string)($context['Oque'] ?? ''));
    $title = planejamento_calendar_trim_title('[Planejamento] ' . ($titleBase !== '' ? $titleBase : 'Iniciativa PPA'));

    $descricao = implode("\n", array_filter([
        'Evento sincronizado automaticamente pelo modulo Planejamento PPA.',
        'Orgao: ' . trim((string)($context['nome_orgao'] ?? '')),
        'Programa: ' . trim((string)($context['nome_programa'] ?? '')),
        'Acao: ' . trim((string)($context['nome_acao'] ?? '')),
        'Meta #' . (int)($context['id_meta'] ?? 0) . ': ' . trim(preg_replace('/\s+/', ' ', (string)($context['indicador'] ?? ''))),
        'Iniciativa #' . (int)($context['id_iniciativa'] ?? 0),
        'Quem: ' . trim((string)($context['Quem'] ?? '')),
        'Quanto: ' . trim((string)($context['Quanto'] ?? '')),
        'Justificativa: ' . trim(preg_replace('/\s+/', ' ', (string)($context['Justificativa'] ?? ''))),
    ]));

    return [
        'titulo' => $title,
        'descricao' => $descricao,
        'inicio' => $inicio,
        'fim' => null,
        'all_day' => 1,
        'local' => trim((string)($context['Onde'] ?? '')),
    ];
}

function planejamento_calendar_reset_event_relations(mysqli $conn, int $eventId, array $recipients): void
{
    $conn->query('DELETE FROM evento_destinatarios WHERE evento_id = ' . $eventId);
    $conn->query('DELETE FROM evento_usuarios WHERE evento_id = ' . $eventId);
    $conn->query('DELETE FROM evento_unidades WHERE evento_id = ' . $eventId);

    $stmt = $conn->prepare('INSERT INTO evento_destinatarios (evento_id, matricula) VALUES (?, ?)');
    foreach ($recipients as $matricula) {
        $stmt->bind_param('ii', $eventId, $matricula);
        $stmt->execute();
    }
    $stmt->close();
}

function planejamento_calendar_upsert_iniciativa(PDO $pdo, array $context, int $creatorMatricula): void
{
    $calendarConn = db();
    $payload = planejamento_calendar_build_payload($context);
    $eventId = planejamento_calendar_lookup_event_id($pdo, (int)$context['id_iniciativa']);
    $eventExists = $eventId !== null && planejamento_calendar_event_exists($calendarConn, $eventId);
    $recipients = planejamento_calendar_recipients($calendarConn, $creatorMatricula);

    $calendarConn->begin_transaction();

    try {
        if ($eventExists) {
            $stmt = $calendarConn->prepare(
                'UPDATE eventos
                 SET titulo = ?, descricao = ?, inicio = ?, fim = ?, all_day = ?, local = ?
                 WHERE id = ?'
            );
            $stmt->bind_param(
                'ssssisi',
                $payload['titulo'],
                $payload['descricao'],
                $payload['inicio'],
                $payload['fim'],
                $payload['all_day'],
                $payload['local'],
                $eventId
            );
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $calendarConn->prepare(
                'INSERT INTO eventos (titulo, descricao, inicio, fim, all_day, local, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'ssssisi',
                $payload['titulo'],
                $payload['descricao'],
                $payload['inicio'],
                $payload['fim'],
                $payload['all_day'],
                $payload['local'],
                $creatorMatricula
            );
            $stmt->execute();
            $eventId = (int)$stmt->insert_id;
            $stmt->close();
        }

        planejamento_calendar_reset_event_relations($calendarConn, (int)$eventId, $recipients);
        $calendarConn->commit();
    } catch (Throwable $e) {
        $calendarConn->rollback();
        throw $e;
    }

    planejamento_calendar_store_mapping($pdo, (int)$context['id_iniciativa'], (int)$eventId);
}

function planejamento_calendar_delete_iniciativa(PDO $pdo, int $idIniciativa): void
{
    $eventId = planejamento_calendar_lookup_event_id($pdo, $idIniciativa);
    if ($eventId === null) {
        return;
    }

    $calendarConn = db();
    $calendarConn->begin_transaction();

    try {
        $calendarConn->query('DELETE FROM notificacoes WHERE evento_id = ' . $eventId);
        $calendarConn->query('DELETE FROM evento_destinatarios WHERE evento_id = ' . $eventId);
        $calendarConn->query('DELETE FROM evento_usuarios WHERE evento_id = ' . $eventId);
        $calendarConn->query('DELETE FROM evento_unidades WHERE evento_id = ' . $eventId);
        $calendarConn->query('DELETE FROM eventos WHERE id = ' . $eventId);
        $calendarConn->commit();
    } catch (Throwable $e) {
        $calendarConn->rollback();
        throw $e;
    }

    planejamento_calendar_delete_mapping($pdo, $idIniciativa);
}
