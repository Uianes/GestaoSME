<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/first_access.php';
require_login();

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
$matricula = (int)($_SESSION['user']['matricula'] ?? 0);
if ($matricula <= 0) {
  echo '<div class="alert alert-danger">Sessão inválida. Faça login novamente.</div>';
  return;
}
if (!first_access_needs_update($matricula)) {
  header('Location: app.php?page=home');
  exit;
}
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h2 class="mb-2 text-body-emphasis">Primeiro acesso</h2>
    <div class="text-muted">Atualize seus dados e defina uma nova senha.</div>
  </div>
</div>

<?php if ($flashError): ?>
  <div class="alert alert-danger" role="alert"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card shadow-sm" style="max-width: 640px;">
  <div class="card-body">
    <form method="post" action="<?= url('actions/primeiro_acesso.php') ?>">
      <div class="mb-3">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Telefone</label>
        <input type="text" name="telefone" class="form-control" required maxlength="15" placeholder="(DD) 9 000000000" inputmode="numeric">
      </div>
      <div class="mb-3">
        <label class="form-label">Nova senha</label>
        <input type="password" name="senha" class="form-control" required minlength="6">
      </div>
      <div class="mb-3">
        <label class="form-label">Confirmar senha</label>
        <input type="password" name="senha_confirmacao" class="form-control" required minlength="6">
      </div>
  <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>

<script>
  (function () {
    const input = document.querySelector('input[name="telefone"]');
    if (!input) return;

    function formatPhone(value) {
      const digits = value.replace(/\D/g, '').slice(0, 12);
      const ddd = digits.slice(0, 2);
      const nine = digits.slice(2, 3);
      const part1 = digits.slice(3, 8);
      const part2 = digits.slice(8, 12);
      let result = '';
      if (ddd) result += `(${ddd})`;
      if (nine) result += ` ${nine}`;
      if (part1) result += ` ${part1}`;
      if (part2) result += `${part2}`;
      return result.trim();
    }

    function handleInput() {
      input.value = formatPhone(input.value);
    }

    input.addEventListener('input', handleInput);
    input.addEventListener('blur', handleInput);
    handleInput();
  })();
</script>
