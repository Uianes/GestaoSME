<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$user = $_SESSION['user'] ?? null;
$activePage = $activePage ?? 'home';
$links = [
  'home' => ['label' => 'Início', 'icon' => 'bi-house', 'href' => url('index.php')],
  'certificados' => ['label' => 'Certificados', 'icon' => 'bi-patch-check', 'href' => url('index.php?page=certificados')],
  'documentos' => ['label' => 'Documentos', 'icon' => 'bi-folder2', 'href' => url('index.php?page=documentos')],
  'indicadores' => ['label' => 'Indicadores', 'icon' => 'bi-bar-chart', 'href' => url('index.php?page=indicadores')],
];
function sa_sidebar_link(string $key, array $link, string $activePage): string
{
  $active = ($key === $activePage) ? ' sa-link-active' : '';
  $icon = htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8');
  $label = htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8');
  $href = htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8');
  return "<a class=\"sa-link{$active}\" href=\"{$href}\"><i class=\"bi {$icon} me-2\"></i>{$label}</a>";
}
ob_start();
$primeiroNome = 'Usuário';
if (!empty($user) && !empty($user['nome'])) {
  $partes = preg_split('/\s+/', trim($user['nome']));
  $primeiroNome = $partes[0];
}
?>
<div class="p-3">
  <div class="input-group sa-search">
    <span class="input-group-text bg-white border-end-0">
      <i class="bi bi-search"></i>
    </span>
    <input type="text" class="form-control border-start-0" placeholder="Pesquisar...">
  </div>
</div>
<div class="sa-sidebar-scroll px-2 pb-3">
  <div class="sa-section-title px-2 mt-2">Workspace</div>
  <ul class="list-unstyled sa-tree">
    <li class="sa-item">
      <?= sa_sidebar_link('home', $links['home'], $activePage) ?>
    </li>
    <li class="sa-item">
      <button class="sa-link sa-link-btn" type="button" data-bs-toggle="collapse" data-bs-target="#wsSistemas"
        aria-expanded="true">
        <i class="bi bi-grid-3x3-gap me-2"></i>
        <span class="flex-grow-1">Sistemas</span>
        <i class="bi bi-chevron-down sa-caret"></i>
      </button>
      <div class="collapse show" id="wsSistemas">
        <ul class="list-unstyled sa-sub">
          <li class="sa-item"><?= sa_sidebar_link('certificados', $links['certificados'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('documentos', $links['documentos'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('indicadores', $links['indicadores'], $activePage) ?></li>
        </ul>
      </div>
    </li>
    <li class="sa-item">
      <a class="sa-link" href="#">
        <i class="bi bi-life-preserver me-2"></i> Suporte técnico
      </a>
    </li>
  </ul>
  <div class="sa-section-title px-2 mt-3">Private</div>
  <ul class="list-unstyled sa-tree">
    <li class="sa-item">
      <button class="sa-link sa-link-btn" type="button" data-bs-toggle="collapse" data-bs-target="#pvCatalog"
        aria-expanded="true">
        <i class="bi bi-folder2-open me-2"></i>
        <span class="flex-grow-1">Meus atalhos</span>
        <i class="bi bi-chevron-down sa-caret"></i>
      </button>
      <div class="collapse show" id="pvCatalog">
        <ul class="list-unstyled sa-sub">
          <li class="sa-item"><a class="sa-link" href="#"><i class="bi bi-star me-2"></i> Favoritos</a></li>
          <li class="sa-item"><a class="sa-link" href="#"><i class="bi bi-clock-history me-2"></i> Recentes</a></li>
        </ul>
      </div>
    </li>
  </ul>
</div>
<div class="border-top p-3 mt-auto">
  <div class="dropdown">
    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown"
      aria-expanded="false">
      <?php
      $avatar = $_SESSION['user']['avatar'] ?? null;
      $avatarUrl = $avatar
        ? ('uploads/avatars/' . $avatar)
        : asset('../img/avatardefault.svg');
      ?>
      <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" width="32" height="32"
        class="rounded-circle me-2 border" style="object-fit: cover;">
      <div class="small">
        <div class="fw-semibold text-dark">
          @<?php echo htmlspecialchars($primeiroNome, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <div class="text-muted" style="font-size:.78rem;">Perfil</div>
      </div>
    </a>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
      <li><a class="dropdown-item" href="app.php?page=config"><i class="bi bi-gear me-2"></i>Configurações</a></li>
      <li><a class="dropdown-item" href="app.php?page=profile"><i class="bi bi-person me-2"></i>Minha conta</a></li>
      <li>
        <hr class="dropdown-divider">
      </li>
      <li><a class="dropdown-item" href="<?= url('auth/logout.php') ?>"><i
            class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
    </ul>
  </div>
</div>
<?php
$sidebarInner = ob_get_clean();
?>
<aside class="sa-sidebar border-end bg-white d-none d-lg-flex flex-column">
  <?= $sidebarInner ?>
</aside>
<div class="offcanvas offcanvas-start" tabindex="-1" id="saSidebar" aria-labelledby="saSidebarLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="saSidebarLabel">Menu</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
  </div>
  <div class="offcanvas-body p-0 d-flex flex-column">
    <div class="sa-sidebar border-end bg-white d-flex flex-column" style="width:100%; min-height:100%">
      <?= $sidebarInner ?>
    </div>
  </div>
</div>