<?php
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../config/db.php';

if (!user_is_admin()) {
    ?>
    <div class="alert alert-danger" role="alert">Sem permissão de acesso.</div>
    <?php
    return;
}

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$conn = db();
$links = system_links();
$permissionsTableReady = permissions_table_exists($conn);

$perPage = 20;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$search = trim((string)($_GET['q'] ?? ''));
$searchLike = '%' . $search . '%';

if ($search !== '') {
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM usuarios WHERE nome LIKE ? OR email LIKE ? OR cpf LIKE ? OR matricula LIKE ?');
    $stmt->bind_param('ssss', $searchLike, $searchLike, $searchLike, $searchLike);
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $totalUsers = (int)($totalResult->fetch_assoc()['total'] ?? 0);
    $stmt->close();
} else {
    $totalResult = $conn->query('SELECT COUNT(*) AS total FROM usuarios');
    $totalUsers = (int)($totalResult->fetch_assoc()['total'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;

$users = [];
$query = 'SELECT matricula, nome, email, cpf, ativo, ADM FROM usuarios';
if ($search !== '') {
    $query .= ' WHERE nome LIKE ? OR email LIKE ? OR cpf LIKE ? OR matricula LIKE ?';
}
$query .= ' ORDER BY nome LIMIT ? OFFSET ?';
$stmt = $conn->prepare($query);
if ($search !== '') {
    $stmt->bind_param('ssssii', $searchLike, $searchLike, $searchLike, $searchLike, $perPage, $offset);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

$accessMap = [];
if ($permissionsTableReady && !empty($users)) {
    $matriculas = array_map(static function ($user) {
        return (int)$user['matricula'];
    }, $users);
    $placeholders = implode(',', array_fill(0, count($matriculas), '?'));
    $types = str_repeat('i', count($matriculas));
    $stmt = $conn->prepare("SELECT matricula, sistema FROM usuarios_sistemas WHERE matricula IN ($placeholders)");
    $params = array_merge([$types], $matriculas);
    $refs = [];
    foreach ($params as $index => $value) {
        $refs[$index] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $matricula = (int)$row['matricula'];
        $system = (string)$row['sistema'];
        if (!isset($accessMap[$matricula])) {
            $accessMap[$matricula] = [];
        }
        $accessMap[$matricula][$system] = true;
    }
    $stmt->close();
}
?>

<h2 class="mb-2 text-body-emphasis">Modo Administrador</h2>
<p class="text-muted mb-4">Gerencie o acesso dos usuários aos sistemas.</p>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success" role="alert"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-danger" role="alert"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (!$permissionsTableReady): ?>
  <div class="alert alert-warning" role="alert">
    A tabela <code>usuarios_sistemas</code> não existe. Crie a tabela para liberar o controle de acessos.
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
      <div class="text-muted small">
        Total de usuários: <?= $totalUsers ?> • Página <?= $currentPage ?> de <?= $totalPages ?>
      </div>
      <form class="d-flex align-items-center gap-2" method="get" action="<?= url('app.php') ?>">
        <input type="hidden" name="page" value="admin">
        <input class="form-control form-control-sm" type="search" name="q" placeholder="Buscar pessoa..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        <button class="btn btn-outline-secondary btn-sm" type="submit">Buscar</button>
        <?php if ($search !== ''): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?= url('app.php?page=admin') ?>">Limpar</a>
        <?php endif; ?>
      </form>
      <?php
        $baseParams = $_GET;
        unset($baseParams['p']);
        $baseQuery = http_build_query($baseParams);
        $baseUrl = url('app.php?page=admin' . ($baseQuery ? '&' . $baseQuery : ''));
      ?>
      <nav aria-label="Paginação de usuários">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $baseUrl . '&p=' . max(1, $currentPage - 1) ?>">Anterior</a>
          </li>
          <?php for ($page = 1; $page <= $totalPages; $page++): ?>
            <li class="page-item <?= $page === $currentPage ? 'active' : '' ?>">
              <a class="page-link" href="<?= $baseUrl . '&p=' . $page ?>"><?= $page ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $baseUrl . '&p=' . min($totalPages, $currentPage + 1) ?>">Próxima</a>
          </li>
        </ul>
      </nav>
    </div>
    <div class="table-responsive">
      <form method="post" action="<?= url('actions/admin_access_update.php') ?>">
        <table class="table table-sm table-striped align-middle">
          <thead>
            <tr>
              <th>Usuário</th>
              <th>Matrícula</th>
              <th>Email</th>
              <th>Ativo</th>
              <th>ADM</th>
              <?php foreach ($links as $key => $link): ?>
                <th class="text-center" style="min-width: 140px;">
                  <?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
              <?php
                $matricula = (int)$user['matricula'];
                $userAccess = $accessMap[$matricula] ?? [];
              ?>
              <tr>
                <td>
                  <input type="hidden" name="access[<?= $matricula ?>][]" value="">
                  <?= htmlspecialchars((string)$user['nome'], ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td><?= htmlspecialchars((string)$user['matricula'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-center">
                  <input type="hidden" name="ativo[<?= $matricula ?>]" value="0">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    name="ativo[<?= $matricula ?>]"
                    value="1"
                    <?= (int)$user['ativo'] === 1 ? 'checked' : '' ?>
                    <?= !$permissionsTableReady ? 'disabled' : '' ?>
                  >
                </td>
                <td><?= (int)$user['ADM'] === 1 ? 'Sim' : 'Não' ?></td>
                <?php foreach ($links as $key => $link): ?>
                  <td class="text-center">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      name="access[<?= $matricula ?>][]"
                      value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                      <?= !empty($userAccess[$key]) ? 'checked' : '' ?>
                      <?= !$permissionsTableReady ? 'disabled' : '' ?>
                    >
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary" <?= !$permissionsTableReady ? 'disabled' : '' ?>>Salvar acessos</button>
        </div>
      </form>
    </div>
  </div>
</div>
