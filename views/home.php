<?php
require_once __DIR__ . '/../auth/session.php';
$user = $_SESSION['user'] ?? null;
?>
<h2 class="mb-2">Página inicial</h2>
<p class="text-muted mb-4">
    Bem-vindo<?php echo $user ? ', ' . htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') : ''; ?>.
</p>

<div class="card">
  <div class="card-body">
    <h5 class="card-title mb-2">Conexão com o banco OK ✅</h5>
    <p class="card-text mb-0">
      A partir daqui você pode começar a construir as telas do sistema (documentos, certificados, indicadores…).
    </p>
  </div>
</div>
