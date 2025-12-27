<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';
require_login();
$sessionUser = $_SESSION['user'] ?? null;
$matricula = (int) ($sessionUser['matricula'] ?? 0);
if ($matricula <= 0) {
  echo '<div class="alert alert-danger">Sessão inválida. Faça login novamente.</div>';
  exit;
}
$conn = db();
$stmt = $conn->prepare("
  SELECT matricula, cpf, nome, rg, data_nascimento, email, telefone, ativo
  FROM usuarios
  WHERE matricula = ?
  LIMIT 1
");
$stmt->bind_param("i", $matricula);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
  echo '<div class="alert alert-warning">Usuário não encontrado na tabela usuarios.</div>';
  exit;
}
$stmt = $conn->prepare("
  SELECT vinculo.matricula, vinculo.regime, vinculo.data_admissao, vinculo.cargo, vinculo.nivel, vinculo.classe, orgaos.nome_orgao, unidade.nome
  FROM ((vinculo INNER JOIN orgaos ON vinculo.id_orgao = orgaos.id_orgao) INNER JOIN unidade ON vinculo.id_unidade = unidade.id_unidade)
  WHERE matricula = ? ORDER BY data_admissao DESC, id_vinculo DESC
");
$stmt->bind_param("i", $matricula);
$stmt->execute();
$vinculos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
function h($v)
{
  return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
function fmt_date($d)
{
  if (!$d)
    return '-';
  $ts = strtotime($d);
  return $ts ? date('d/m/Y', $ts) : h($d);
}
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h3 class="mb-0">Minha conta</h3>
    <div class="text-muted">Dados cadastrais e vínculos</div>
  </div>
</div>
<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <?php
          $avatar = $_SESSION['user']['avatar'] ?? null;
          $avatarUrl = $avatar
            ? ('uploads/avatars/' . $avatar)
            : asset('../img/avatardefault.svg');
          ?>
          <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" width="48" height="48"
            class="rounded-circle me-2 border" style="object-fit: cover;">
          <div>
            <div class="fw-semibold" style="font-size:1.05rem;"><?= h($user['nome']) ?></div>
          </div>
        </div>
        <div class="row g-2 small">
          <div class="col-6"><span class="text-muted">Matrícula</span>
            <div class="fw-semibold"><?= h($user['matricula']) ?></div>
          </div>
          <div class="col-6"><span class="text-muted">CPF</span>
            <div class="fw-semibold"><?= h($user['cpf']) ?></div>
          </div>
          <div class="col-6"><span class="text-muted">RG</span>
            <div class="fw-semibold"><?= $user['rg'] ? h($user['rg']) : '<span class="text-muted">—</span>' ?></div>
          </div>
          <div class="col-6"><span class="text-muted">Nascimento</span>
            <div class="fw-semibold"><?= fmt_date($user['data_nascimento']) ?></div>
          </div>
          <div class="col-12"><span class="text-muted">E-mail</span>
            <div class="fw-semibold"><?= $user['email'] ? h($user['email']) : '<span class="text-muted">—</span>' ?>
            </div>
          </div>
          <div class="col-12"><span class="text-muted">Telefone</span>
            <div class="fw-semibold">
              <?= $user['telefone'] ? h($user['telefone']) : '<span class="text-muted">—</span>' ?>
            </div>
          </div>
          <div class="col-12 mt-2">
            <?php if ((int) $user['ativo'] === 1): ?>
              <span class="badge text-bg-success">Ativo</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">Inativo</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="fw-semibold">Vínculos</div>
          <span class="badge text-bg-light border"><?= count($vinculos) ?></span>
        </div>
        <?php if (!$vinculos): ?>
          <div class="text-muted">Nenhum vínculo encontrado para esta matrícula.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr class="text-muted small">
                  <th>Cargo</th>
                  <th>Regime</th>
                  <th>Admissão</th>
                  <th>Nível</th>
                  <th>Classe</th>
                  <th>Unidade</th>
                  <th>Órgão</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($vinculos as $v): ?>
                  <tr>
                    <td class="fw-semibold"><?= h($v['cargo']) ?></td>
                    <td><?= h($v['regime']) ?></td>
                    <td><?= fmt_date($v['data_admissao']) ?></td>
                    <td><?= h($v['nivel']) ?></td>
                    <td><?= $v['classe'] ? h($v['classe']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= h($v['nome_orgao']) ?></td>
                    <td><?= h($v['nome']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>