<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';
require_login();
$conn = db();
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM IBGE2020");
if (!$res) {
  echo "<div class='alert alert-danger'>Tabela IBGE2020 não encontrada.</div>";
  exit;
}
while ($c = $res->fetch_assoc()) {
  $field = $c['Field'];
  $type  = strtolower($c['Type']);
  if ($field === 'idade') continue;
  if (preg_match('/int|decimal|float|double|numeric/', $type)) {
    $cols[] = $field;
  }
}
if (count($cols) === 0) {
  echo "<div class='alert alert-warning'>A tabela IBGE2020 não tem colunas numéricas além de 'idade'.</div>";
  exit;
}
$selectCols = implode(", ", array_map(fn($c) => "`$c`", $cols));
$sql = "SELECT `idade`, $selectCols FROM IBGE2020";
$data = [];
$res2 = $conn->query($sql);
if ($res2) {
  while ($r = $res2->fetch_assoc()) {
    $data[] = $r;
  }
}
$labels = array_map(fn($r) => (string)$r['idade'], $data);
$datasets = [];
foreach ($cols as $col) {
  $series = array_map(function($r) use ($col) {
    $v = $r[$col] ?? 0;
    return is_numeric($v) ? (float)$v : 0;
  }, $data);
  $datasets[] = [
    'label' => $col,
    'data'  => $series,
  ];
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h3 class="mb-0 text-body-emphasis">IBGE 2020</h3>
    <div class="text-muted">Gráfico gerado a partir da tabela <code>IBGE2020</code></div>
  </div>
</div>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="fw-semibold">Distribuição por idade</div>
      <span class="badge text-bg-light border">
        Séries: <?= count($cols) ?>
      </span>
    </div>
    <div class="text-muted small mb-3">
      Colunas usadas: <?= h(implode(", ", $cols)) ?>
    </div>
    <div style="height:420px;">
      <canvas id="ibgeChart"></canvas>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
const datasetsRaw = <?= json_encode($datasets, JSON_UNESCAPED_UNICODE) ?>;
const datasets = datasetsRaw.map((d) => ({
  label: d.label,
  data: d.data,
  borderWidth: 2,
  tension: 0.25,
  fill: false
}));
const ctx = document.getElementById('ibgeChart');
new Chart(ctx, {
  type: 'bar',
  data: { labels, datasets },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: true, position: 'bottom' },
      tooltip: { enabled: true }
    },
    scales: {
      x: { title: { display: true, text: 'Idade' } },
      y: { title: { display: true, text: 'Valor' }, beginAtZero: true }
    }
  }
});
</script>