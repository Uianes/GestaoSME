<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../auth/permissions.php';
$user = $_SESSION['user'] ?? null;
$activePage = $activePage ?? 'home';
$links = require __DIR__ . '/../config/links.php';

function sa_sidebar_link_or_null(string $key, array $link, string $activePage): ?string
{
  if (!user_can_access_system($key)) {
    return null;
  }
  return sa_sidebar_link($key, $link, $activePage);
}
function sa_has_visible_links(array $keys, array $links): bool
{
  foreach ($keys as $key) {
    if (isset($links[$key]) && user_can_access_system($key)) {
      return true;
    }
  }
  return false;
}
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
$showProtocolo = sa_has_visible_links(['protocolo', 'assinatura', 'certificados', 'atestados'], $links);
$showComunicados = sa_has_visible_links(['documentos', 'calendario', 'comunicadosPDDE'], $links);
$showOuvidoria = sa_has_visible_links(['ouvidoria', 'infraestrutura', 'suporte', 'votacoes'], $links);
$showDashboard = sa_has_visible_links(['dashboards'], $links);
$showGestao = sa_has_visible_links(['turmas', 'projetos', 'PPA', 'planosGestao', 'justificativas', 'atestadosSaude', 'progressao', 'patrimonio', 'transporte'], $links);
$showBiblioteca = sa_has_visible_links(['biblioteca', 'mooc'], $links);
$showPedagogico = sa_has_visible_links(['pareces', 'frequencia', 'aee', 'PME', 'horarios'], $links);
?>
<div class="p-3">
  <div class="input-group sa-search">
      <span class="input-group-text bg-body border-end-0">
      <i class="bi bi-search"></i>
    </span>
    <input type="text" class="form-control border-start-0" placeholder="Pesquisar...">
  </div>
</div>
<div class="sa-sidebar-scroll px-2 pb-3">
  <ul class="list-unstyled sa-tree">
    <li class="sa-item">
      <?= sa_sidebar_link('home', $links['home'], $activePage) ?>
    </li>
    <?php if ($showProtocolo): ?>
      <hr>
      <li class="sa-item">
        <button class="sa-link sa-link-btn" type="button" data-bs-toggle="collapse" data-bs-target="#wsProtocolo"
          aria-expanded="true">
          <i class="bi bi-grid-3x3-gap me-2"></i>
          <span class="flex-grow-1">Protocolo</span>
          <i class="bi bi-chevron-down sa-caret"></i>
        </button>
        <div class="collapse show" id="wsProtocolo">
          <ul class="list-unstyled sa-sub">
            <?php if (isset($links['protocolo']) && ($link = sa_sidebar_link_or_null('protocolo', $links['protocolo'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['assinatura']) && ($link = sa_sidebar_link_or_null('assinatura', $links['assinatura'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['certificados']) && ($link = sa_sidebar_link_or_null('certificados', $links['certificados'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['atestados']) && ($link = sa_sidebar_link_or_null('atestados', $links['atestados'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
    <?php endif; ?>
    <?php if ($showComunicados): ?>
      <hr>
      <li class="sa-item">
        <button class="sa-link sa-link-btn" type="button" data-bs-toggle="collapse" data-bs-target="#wsComunicados"
          aria-expanded="true">
          <i class="bi bi-grid-3x3-gap me-2"></i>
          <span class="flex-grow-1">Comunicados</span>
          <i class="bi bi-chevron-down sa-caret"></i>
        </button>
        <div class="collapse show" id="wsComunicados">
          <ul class="list-unstyled sa-sub">
            <?php if (isset($links['documentos']) && ($link = sa_sidebar_link_or_null('documentos', $links['documentos'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['calendario']) && ($link = sa_sidebar_link_or_null('calendario', $links['calendario'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['comunicadosPDDE']) && ($link = sa_sidebar_link_or_null('comunicadosPDDE', $links['comunicadosPDDE'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
    <?php endif; ?>
    <?php if ($showOuvidoria): ?>
      <hr>
      <li class="sa-item">
        <button class="sa-link sa-link-btn" type="button" data-bs-toggle="collapse" data-bs-target="#wsOuvidoria"
          aria-expanded="true">
          <i class="bi bi-grid-3x3-gap me-2"></i>
          <span class="flex-grow-1">Ouvidoria</span>
          <i class="bi bi-chevron-down sa-caret"></i>
        </button>
        <div class="collapse show" id="wsOuvidoria">
          <ul class="list-unstyled sa-sub">
            <?php if (isset($links['ouvidoria']) && ($link = sa_sidebar_link_or_null('ouvidoria', $links['ouvidoria'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['infraestrutura']) && ($link = sa_sidebar_link_or_null('infraestrutura', $links['infraestrutura'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['suporte']) && ($link = sa_sidebar_link_or_null('suporte', $links['suporte'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['votacoes']) && ($link = sa_sidebar_link_or_null('votacoes', $links['votacoes'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
    <?php endif; ?>
    <?php if ($showDashboard): ?>
      <hr>
      <li class="sa-item">
        <button class="sa-link sa-link-btn" type="button" data-bs-toggle="collapse" data-bs-target="#wsDashboard"
          aria-expanded="true">
          <i class="bi bi-grid-3x3-gap me-2"></i>
          <span class="flex-grow-1">Dados abertos</span>
          <i class="bi bi-chevron-down sa-caret"></i>
        </button>
        <div class="collapse show" id="wsDashboard">
          <ul class="list-unstyled sa-sub">
            <?php if (isset($links['dashboards']) && ($link = sa_sidebar_link_or_null('dashboards', $links['dashboards'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
    <?php endif; ?>
    <?php if ($showGestao): ?>
      <hr>
      <li class="sa-item">
        <button class="sa-link sa-link-btn" type="button" data-bs-toggle="collapse" data-bs-target="#wsGestao"
          aria-expanded="true">
          <i class="bi bi-grid-3x3-gap me-2"></i>
          <span class="flex-grow-1">Gestão</span>
          <i class="bi bi-chevron-down sa-caret"></i>
        </button>
        <div class="collapse show" id="wsGestao">
          <ul class="list-unstyled sa-sub">
            <?php if (isset($links['turmas']) && ($link = sa_sidebar_link_or_null('turmas', $links['turmas'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['projetos']) && ($link = sa_sidebar_link_or_null('projetos', $links['projetos'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['PPA']) && ($link = sa_sidebar_link_or_null('PPA', $links['PPA'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['planosGestao']) && ($link = sa_sidebar_link_or_null('planosGestao', $links['planosGestao'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['justificativas']) && ($link = sa_sidebar_link_or_null('justificativas', $links['justificativas'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['atestadosSaude']) && ($link = sa_sidebar_link_or_null('atestadosSaude', $links['atestadosSaude'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['progressao']) && ($link = sa_sidebar_link_or_null('progressao', $links['progressao'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['patrimonio']) && ($link = sa_sidebar_link_or_null('patrimonio', $links['patrimonio'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['transporte']) && ($link = sa_sidebar_link_or_null('transporte', $links['transporte'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
    <?php endif; ?>
    <?php if ($showBiblioteca): ?>
      <hr>
      <li class="sa-item">
        <button class="sa-link sa-link-btn" type="button" data-bs-toggle="collapse" data-bs-target="#wsBiblioteca"
          aria-expanded="true">
          <i class="bi bi-grid-3x3-gap me-2"></i>
          <span class="flex-grow-1">Biblioteca</span>
          <i class="bi bi-chevron-down sa-caret"></i>
        </button>
        <div class="collapse show" id="wsBiblioteca">
          <ul class="list-unstyled sa-sub">
            <?php if (isset($links['biblioteca']) && ($link = sa_sidebar_link_or_null('biblioteca', $links['biblioteca'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['mooc']) && ($link = sa_sidebar_link_or_null('mooc', $links['mooc'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
    <?php endif; ?>
    <?php if ($showPedagogico): ?>
      <hr>
      <li class="sa-item">
        <button class="sa-link sa-link-btn" type="button" data-bs-toggle="collapse" data-bs-target="#wsPedagogico"
          aria-expanded="true">
          <i class="bi bi-grid-3x3-gap me-2"></i>
          <span class="flex-grow-1">Pedagógico</span>
          <i class="bi bi-chevron-down sa-caret"></i>
        </button>
        <div class="collapse show" id="wsPedagogico">
          <ul class="list-unstyled sa-sub">
            <?php if (isset($links['pareces']) && ($link = sa_sidebar_link_or_null('pareces', $links['pareces'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['frequencia']) && ($link = sa_sidebar_link_or_null('frequencia', $links['frequencia'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['aee']) && ($link = sa_sidebar_link_or_null('aee', $links['aee'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['PME']) && ($link = sa_sidebar_link_or_null('PME', $links['PME'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
            <?php if (isset($links['horarios']) && ($link = sa_sidebar_link_or_null('horarios', $links['horarios'], $activePage))): ?>
              <li class="sa-item"><?= $link ?></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
    <?php endif; ?>
  </ul>
  <hr>
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
          <?php if (user_is_admin()): ?>
            <li class="sa-item"><a class="sa-link" href="<?= url('app.php?page=admin') ?>"><i class="bi bi-star me-2"></i> Modo Administrador</a></li>
          <?php endif; ?>
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
          <div class="fw-semibold text-body">
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
<aside class="sa-sidebar border-end bg-body d-none d-lg-flex flex-column">
  <?= $sidebarInner ?>
</aside>
<div class="offcanvas offcanvas-start" tabindex="-1" id="saSidebar" aria-labelledby="saSidebarLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="saSidebarLabel">Menu</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
  </div>
  <div class="offcanvas-body p-0 d-flex flex-column">
    <div class="sa-sidebar border-end bg-body d-flex flex-column" style="width:100%; min-height:100%">
      <?= $sidebarInner ?>
    </div>
  </div>
</div>
