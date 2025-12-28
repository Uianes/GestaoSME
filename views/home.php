<?php
require_once __DIR__ . '/../auth/session.php';
$user = $_SESSION['user'] ?? null;
?>
<h2 class="mb-2 text-body-emphasis">Página inicial</h2>
<p class="text-muted mb-4">
    Bem-vindo<?php echo $user ? ', ' . htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') : ''; ?>.
</p>

<div class="card">
  <div class="card-body">
    <h5 class="card-title mb-2">Lorem Ipsum</h5>
    <p class="card-text mb-0">
      Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed non tincidunt orci, sit amet ultrices nibh. Vivamus dolor odio, porta eu fermentum vitae, tempor nec augue. Mauris in magna bibendum, volutpat mauris a, tristique dolor. In aliquam mauris et sodales sagittis. Nam metus tellus, dignissim id elit at, sodales cursus ligula. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Cras fermentum neque nec finibus dictum.
    </p>
  </div>
</div>