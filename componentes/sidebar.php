<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$user = $_SESSION['user'] ?? null;
$activePage = $activePage ?? 'home';
$links = [
  'home' => ['label' => 'Início', 'icon' => 'bi-house', 'href' => url('app.php')],
  'protocolo' => ['label' => 'Protocolo Eletrônico', 'icon' => 'bi-file-text-fill', 'href' => url('index.php?page=protocolo')],
  'documentos' => ['label' => 'Repositório de Documentos', 'icon' => 'bi-chat-left-text-fill', 'href' => url('index.php?page=documentos')],
  'assinatura' => ['label' => 'Assinatura Digital', 'icon' => 'bi-pencil-square', 'href' => url('index.php?page=assinatura')],
  'certificados' => ['label' => 'Certificados', 'icon' => 'bi-file-earmark-medical-fill', 'href' => url('index.php?page=certificados')],
  'atestados' => ['label' => 'Atestados', 'icon' => 'bi-file-earmark-post', 'href' => url('index.php?page=atestados')],
  'ouvidoria' => ['label' => 'Ouvidoria', 'icon' => 'bi-bookmark-plus', 'href' => url('index.php?page=ouvidoria')],
  'dashboards' => ['label' => 'Dashboards', 'icon' => 'bi-bar-chart', 'href' => url('index.php?page=dashboards')],
  'turmas' => ['label' => 'Minhas turmas', 'icon' => 'bi-mortarboard', 'href' => url('index.php?page=turmas')],
  'projetos' => ['label' => 'Meus projetos', 'icon' => 'bi-award', 'href' => url('index.php?page=projetos')],
  'biblioteca' => ['label' => 'Biblioteca', 'icon' => 'bi-book', 'href' => url('index.php?page=biblioteca')],
  'pareces' => ['label' => 'Meus pareces', 'icon' => 'bi-ui-checks', 'href' => url('index.php?page=pareces')],
  'frequencia' => ['label' => 'Monitoramento frequência', 'icon' => 'bi-percent', 'href' => url('index.php?page=frequencia')],
  'aee' => ['label' => 'PAEE e PEI', 'icon' => 'bi-easel2', 'href' => url('index.php?page=aee')],
  'infraestrutura' => ['label' => 'Avaliação de Infraestrutura', 'icon' => 'bi-building-check', 'href' => url('index.php?page=infraestrutura')],
  'PME' => ['label' => 'Plano Municipal de Educação', 'icon' => 'bi-card-list', 'href' => url('index.php?page=pme')],
  'PPA' => ['label' => 'Plano Plurianual', 'icon' => 'bi-cash-coin', 'href' => url('index.php?page=ppa')],
  'planosGestao' => ['label' => 'Planos de Gestão', 'icon' => 'bi-postcard', 'href' => url('index.php?page=planosGestao')],
  'calendario' => ['label' => 'Calendário', 'icon' => 'bi-calendar', 'href' => url('index.php?page=calendario')],
  'justificativas' => ['label' => 'Justificativas do Ponto', 'icon' => 'bi-calendar-plus', 'href' => url('index.php?page=justificativas')],
  'horarios' => ['label' => 'Construir Horários', 'icon' => 'bi-table', 'href' => url('index.php?page=horarios')],
  'atestadosSaude' => ['label' => 'Atestados de Saúde', 'icon' => 'bi-bandaid', 'href' => url('index.php?page=atestadosSaude')],
  'mooc' => ['label' => 'Cursos', 'icon' => 'bi-journals', 'href' => url('index.php?page=cursos')],
  'progressao' => ['label' => 'Gestão de Certificados', 'icon' => 'bi-person-workspace', 'href' => url('index.php?page=progressao')],
  'comunicadosPDDE' => ['label' => 'Comunicados PDDE', 'icon' => 'bi-chat-quote-fill', 'href' => url('index.php?page=comunicadosPDDE')],
  'patrimonio' => ['label' => 'Patrimônio', 'icon' => 'bi-music-player', 'href' => url('index.php?page=patrimonio')],
  'suporte' => ['label' => 'Suporte', 'icon' => 'bi-hammer', 'href' => url('index.php?page=suporte')],
  'transporte' => ['label' => 'Transporte Escolar', 'icon' => 'bi-bus-front', 'href' => url('index.php?page=transporte')],
  'votacoes' => ['label' => 'Votações', 'icon' => 'bi-person-plus', 'href' => url('index.php?page=votacoes')]
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
          <li class="sa-item"><?= sa_sidebar_link('protocolo', $links['protocolo'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('assinatura', $links['assinatura'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('certificados', $links['certificados'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('atestados', $links['atestados'], $activePage) ?></li>
        </ul>
      </div>
    </li>
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
          <li class="sa-item"><?= sa_sidebar_link('documentos', $links['documentos'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('calendario', $links['calendario'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('comunicadosPDDE', $links['comunicadosPDDE'], $activePage) ?></li>
        </ul>
      </div>
    </li>
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
          <li class="sa-item"><?= sa_sidebar_link('ouvidoria', $links['ouvidoria'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('infraestrutura', $links['infraestrutura'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('suporte', $links['suporte'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('votacoes', $links['votacoes'], $activePage) ?></li>
        </ul>
      </div>
    </li>
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
          <li class="sa-item"><?= sa_sidebar_link('dashboards', $links['dashboards'], $activePage) ?></li>
        </ul>
      </div>
    </li>
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
          <li class="sa-item"><?= sa_sidebar_link('turmas', $links['turmas'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('projetos', $links['projetos'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('PPA', $links['PPA'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('planosGestao', $links['planosGestao'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('justificativas', $links['justificativas'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('atestadosSaude', $links['atestadosSaude'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('progressao', $links['progressao'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('patrimonio', $links['patrimonio'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('transporte', $links['transporte'], $activePage) ?></li>
        </ul>
      </div>
    </li>
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
          <li class="sa-item"><?= sa_sidebar_link('biblioteca', $links['biblioteca'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('mooc', $links['mooc'], $activePage) ?></li>
        </ul>
      </div>
    </li>
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
          <li class="sa-item"><?= sa_sidebar_link('pareces', $links['pareces'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('frequencia', $links['frequencia'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('aee', $links['aee'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('PME', $links['PME'], $activePage) ?></li>
          <li class="sa-item"><?= sa_sidebar_link('horarios', $links['horarios'], $activePage) ?></li>
        </ul>
      </div>
    </li>
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