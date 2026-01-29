<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';
$user = $_SESSION['user'] ?? null;
$matricula = (int)($user['matricula'] ?? 0);

$inicioSemana = new DateTime('monday this week');
$inicioSemana->setTime(0, 0, 0);
$fimSemana = clone $inicioSemana;
$fimSemana->modify('+6 days')->setTime(23, 59, 59);

$eventosSemana = [];
if ($matricula > 0) {
    $conn = db();
    $stmt = $conn->prepare('
        SELECT e.id, e.titulo, e.inicio, e.fim, e.all_day, e.local
        FROM eventos e
        INNER JOIN evento_destinatarios d ON d.evento_id = e.id
        WHERE d.matricula = ?
          AND e.inicio <= ?
          AND (e.fim IS NULL OR e.fim >= ?)
        ORDER BY e.inicio ASC
    ');
    $inicioSemanaStr = $inicioSemana->format('Y-m-d H:i:s');
    $fimSemanaStr = $fimSemana->format('Y-m-d H:i:s');
    $stmt->bind_param('iss', $matricula, $fimSemanaStr, $inicioSemanaStr);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $eventosSemana[] = $row;
    }
    $stmt->close();
}

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fmt_datetime($value, $allDay)
{
    if (!$value) {
        return 'O dia todo';
    }
    $ts = strtotime($value);
    if (!$ts) {
        return h($value);
    }
    return $allDay ? date('d/m/Y', $ts) . ' • O dia todo' : date('d/m/Y H:i', $ts);
}

$mensagens = [
    'Você faz a diferença todos os dias. Obrigado pelo seu trabalho!',
    'Pequenas ações constroem grandes resultados. Vamos juntos!',
    'Cada desafio é uma oportunidade de crescimento.',
    'Seu esforço inspira toda a equipe.',
    'Hoje é um ótimo dia para aprender algo novo.'
];
shuffle($mensagens);
?>
<h2 class="mb-2 text-body-emphasis">Página inicial</h2>
<p class="text-muted mb-4">
    Bem-vindo<?php echo $user ? ', ' . htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') : ''; ?>.
</p>

<div class="card">
  <div class="card-body">
    <h5 class="card-title mb-2">Resumo da semana</h5>
    <div class="text-muted mb-3">
      <?= $inicioSemana->format('d/m/Y') ?> a <?= $fimSemana->format('d/m/Y') ?>
    </div>
    <?php if (!empty($eventosSemana)): ?>
      <div class="list-group">
        <?php foreach ($eventosSemana as $evento): ?>
          <div class="list-group-item">
            <div class="fw-semibold"><?= h($evento['titulo']) ?></div>
            <div class="text-muted small">
              <?= fmt_datetime($evento['inicio'], (int)$evento['all_day'] === 1) ?>
              <?php if (!empty($evento['fim']) && (int)$evento['all_day'] !== 1): ?>
                — <?= date('H:i', strtotime($evento['fim'])) ?>
              <?php endif; ?>
            </div>
            <?php if (!empty($evento['local'])): ?>
              <div class="small">Local: <?= h($evento['local']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="row g-2">
        <?php foreach (array_slice($mensagens, 0, 3) as $mensagem): ?>
          <div class="col-12">
            <div class="alert alert-light border mb-0">
              <?= h($mensagem) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
