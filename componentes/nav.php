<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';
$activePage = $activePage ?? 'home';
$user = $_SESSION['user'] ?? null;
?>
<nav class="navbar bg-body border-bottom px-3 px-lg-4">
  <div class="d-flex align-items-center gap-2">
    <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#saSidebar" aria-controls="saSidebar">
      <i class="bi bi-list"></i>
    </button>
    <a class="navbar-brand fw-semibold" href="#">
      <img src="img/logosmefundo.svg" alt="Logo SME" width="30" height="24" class="d-inline-block align-text-top">
     SME Santo Augusto
    </a>
  </div>
  <div class="d-flex align-items-center gap-2">
    <!-- Toggle Dark/Light -->
    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="SATheme.toggle()" title="Alternar tema">
      <i id="themeIcon" class="bi bi-moon-stars"></i>
    </button>
  <?php if ($user): ?>
    <?php
      $temNotificacoes = false;
      $notiCount = 0;
      $conn = db();
      $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM notificacoes WHERE matricula = ? AND lida_em IS NULL');
      $stmt->bind_param('i', $user['matricula']);
      $stmt->execute();
      $result = $stmt->get_result();
      $notiCount = (int)($result->fetch_assoc()['total'] ?? 0);
      $stmt->close();
      $temNotificacoes = $notiCount > 0;
    ?>
    <a class="btn btn-sm btn-outline-secondary position-relative"
       href="app.php?page=notificacoes"
       title="Notificações">
       
      <?php if ($temNotificacoes): ?>
        <i class="bi bi-bell-fill"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
          <?= $notiCount ?>
        </span>
      <?php else: ?>
        <i class="bi bi-bell"></i>
      <?php endif; ?>
    </a>
  <?php endif; ?>
</div>
</nav>
