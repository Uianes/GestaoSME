<?php
require_once __DIR__ . '/../auth/session.php';
$activePage = $activePage ?? 'home';
$user = $_SESSION['user'] ?? null;
?>
<nav class="navbar navbar-light bg-white border-bottom px-3 px-lg-4">
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
  <?php if ($user): ?>
    <?php
      $temNotificacoes = false;
    ?>
    <a class="btn btn-sm btn-outline-secondary position-relative"
       href="app.php?page=notificacoes"
       title="Notificações">
       
      <?php if ($temNotificacoes): ?>
        <i class="bi bi-bell-fill"></i>
      <?php else: ?>
        <i class="bi bi-bell"></i>
      <?php endif; ?>
    </a>
  <?php endif; ?>
</div>
</nav>
