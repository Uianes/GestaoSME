<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';

$userMatricula = (int)($_SESSION['user']['matricula'] ?? 0);
$conn = db();

$stmt = $conn->prepare('
  SELECT id, titulo, criada_em, lida_em
  FROM notificacoes
  WHERE matricula = ?
  ORDER BY criada_em DESC
  LIMIT 200
');
$stmt->bind_param('i', $userMatricula);
$stmt->execute();
$result = $stmt->get_result();
$notificacoes = [];
while ($row = $result->fetch_assoc()) {
    $notificacoes[] = $row;
}
$stmt->close();
?>

<h2 class="mb-2 text-body-emphasis">Notificações</h2>
<p class="text-muted mb-4">Acompanhe os eventos recebidos.</p>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-end gap-2 mb-3">
      <form method="post" action="<?= url('actions/notifications_mark.php') ?>">
        <input type="hidden" name="action" value="mark_all">
        <button class="btn btn-sm btn-outline-secondary" type="submit">Marcar todas como lidas</button>
      </form>
      <form method="post" action="<?= url('actions/notifications_mark.php') ?>">
        <input type="hidden" name="action" value="delete_read">
        <button class="btn btn-sm btn-outline-danger" type="submit">Remover lidas</button>
      </form>
    </div>

    <?php if (empty($notificacoes)): ?>
      <div class="text-muted">Nenhuma notificação.</div>
    <?php else: ?>
      <ul class="list-group">
        <?php foreach ($notificacoes as $notificacao): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold">
                <?= htmlspecialchars($notificacao['titulo'], ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div class="text-muted small">
                <?= htmlspecialchars($notificacao['criada_em'], ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <?php if (empty($notificacao['lida_em'])): ?>
                <span class="badge bg-primary">Nova</span>
                <form method="post" action="<?= url('actions/notifications_mark.php') ?>">
                  <input type="hidden" name="action" value="mark_one">
                  <input type="hidden" name="id" value="<?= (int)$notificacao['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary" type="submit">Marcar lida</button>
                </form>
              <?php else: ?>
                <span class="badge bg-secondary">Lida</span>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
