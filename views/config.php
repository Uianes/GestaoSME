<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';
require_login();
$user = $_SESSION['user'] ?? null;
$avatar = $user['avatar'] ?? null;
$avatarUrl = $avatar ? url('uploads/avatars/' . $avatar) : asset('img/avatardefault.svg');
$theme = $_SESSION['theme'] ?? ($_COOKIE['sa_theme'] ?? 'light');
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h3 class="mb-0">Configurações</h3>
    <div class="text-muted">Avatar e aparência</div>
  </div>
</div>
<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <img src="<?= h($avatarUrl) ?>" alt="Avatar" width="64" height="64" class="rounded-circle border" style="object-fit:cover;">
          <div>
            <div class="fw-semibold"><?= h($user['nome'] ?? 'Usuário') ?></div>
            <div class="text-muted small">Envie uma foto (JPG/PNG/WebP)</div>
          </div>
        </div>
        <hr>
        <form action="<?= url('actions/upload_avatar.php') ?>" method="post" enctype="multipart/form-data" class="d-flex flex-column gap-2">
          <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp" class="form-control" required>
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-upload me-2"></i>Enviar avatar
          </button>
          <div class="text-muted small">Recomendado: imagem quadrada, mínimo 256×256.</div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2">Aparência</div>
        <div class="text-muted mb-3">Escolha o tema do sistema.</div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" type="button" onclick="SATheme.set('light')">
            <i class="bi bi-sun me-2"></i>Light
          </button>
          <button class="btn btn-outline-secondary" type="button" onclick="SATheme.set('dark')">
            <i class="bi bi-moon-stars me-2"></i>Dark
          </button>
          <button class="btn btn-outline-secondary" type="button" onclick="SATheme.set('system')">
            <i class="bi bi-pc-display me-2"></i>Sistema
          </button>
        </div>
        <div class="mt-3 small text-muted">
          Atual: <span id="saThemeCurrent"><?= h($theme) ?></span>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  document.getElementById('saThemeCurrent').textContent = SATheme.get();
</script>