<?php
// Expected variables: $documento, $versao, $numeracao, $unidadeOrigem, $destinatarios, $assinaturaInfo, $logoSrc
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function format_data_extenso($data) {
    $meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    $ts = strtotime($data);
    if (!$ts) {
        return date('d/m/Y');
    }
    $dia = (int)date('d', $ts);
    $mes = $meses[(int)date('m', $ts) - 1] ?? date('m', $ts);
    $ano = date('Y', $ts);
    return $dia . ' de ' . $mes . ' de ' . $ano;
}

$tipoNome = $documento['tipo_nome'] ?? '';
$numeroFormatado = $numeracao['codigo_formatado'] ?? '—';
$numeroBruto = isset($numeracao['numero'], $numeracao['ano'])
    ? sprintf('%04d/%d', (int)$numeracao['numero'], (int)$numeracao['ano'])
    : $numeroFormatado;
$dataDocumento = $documento['criado_em'] ?? null;
$dataFormatada = $dataDocumento ? format_data_extenso($dataDocumento) : format_data_extenso(date('Y-m-d'));

$de = $unidadeOrigem ?: '—';
$para = $destinatarios ?: ['—'];
$assunto = $documento['assunto'] ?? '';

$assinaturaNome = $assinaturaInfo['nome'] ?? '';
$assinaturaCargo = $assinaturaInfo['cargo'] ?? '';
$assinaturaData = $assinaturaInfo['data'] ?? '';

$tipoExibicao = $tipoNome;
$tipoLower = strtolower($tipoNome);
if (strpos($tipoLower, 'memorando') !== false) {
    $tipoExibicao = 'Memorando';
} elseif (strpos($tipoLower, 'oficio') !== false || strpos($tipoLower, 'ofício') !== false) {
    $tipoExibicao = 'Ofício';
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= h($tipoNome) ?> <?= h($numero) ?></title>
  <style>
    body { font-family: "Times New Roman", serif; color: #111; margin: 32px; }
    .header { display: flex; align-items: center; gap: 18px; margin-bottom: 24px; }
    .header img { height: 90px; }
    .header .org { text-transform: uppercase; font-size: 16px; line-height: 1.4; }
    .linha { display: flex; justify-content: space-between; margin: 8px 0; }
    .linha strong { font-weight: 700; }
    .body { margin: 20px 0 26px; font-size: 16px; line-height: 1.6; }
    .body table { width: 100%; border-collapse: collapse; margin: 8px 0; }
    .body th, .body td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
    .body th { background: #f2f2f2; }
    .footer { margin-top: 32px; display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; }
    .assinatura { text-align: center; min-width: 280px; }
    .assinatura .linha-ass { border-top: 1px solid #000; margin-top: 32px; padding-top: 6px; }
    .recebido { min-width: 200px; }
    .recebido .linha-rec { border-top: 1px solid #000; margin-top: 32px; padding-top: 6px; }
    .meta { margin-bottom: 12px; }
  </style>
</head>
<body>
  <div class="header">
    <?php if (!empty($logoSrc)): ?>
      <img src="<?= h($logoSrc) ?>" alt="Brasão">
    <?php endif; ?>
    <div class="org">
      Estado do Rio Grande do Sul<br>
      Município de Santo Augusto<br>
      Poder Executivo<br>
      Secretaria Municipal de Educação
    </div>
  </div>

  <div class="linha">
    <div>
      <strong>
        <?= h($tipoExibicao) ?>
        <?php if ($de !== '—'): ?>
          <?= h($de) ?>
        <?php endif; ?>
        n° <?= h($numeroBruto) ?>
      </strong>
    </div>
    <div>Santo Augusto, <?= h($dataFormatada) ?></div>
  </div>

  <div class="meta">
    <div><strong>De:</strong> <?= h($de) ?></div>
    <div><strong>Para:</strong> <?= h(implode(', ', $para)) ?></div>
    <div><strong>Assunto:</strong> <?= h($assunto) ?></div>
  </div>

  <div class="body">
    <?= $versao ? $versao['conteudo'] : '<em>Sem conteúdo.</em>' ?>
  </div>

  <div class="footer">
    <div class="recebido">
      <div>Recebido ____/____/______</div>
      <div class="linha-rec"></div>
    </div>
    <div class="assinatura">
      <div><strong>Assinatura</strong></div>
      <div><?= h($assinaturaNome) ?></div>
      <?php if ($assinaturaCargo): ?><div><?= h($assinaturaCargo) ?></div><?php endif; ?>
      <?php if ($assinaturaData): ?><div>Assinado em <?= h($assinaturaData) ?></div><?php endif; ?>
      <div class="linha-ass"></div>
    </div>
  </div>
</body>
</html>
