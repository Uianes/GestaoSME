<?php
// Expected variables: $documento, $versao, $numeracao, $unidadeOrigem, $destinatarios, $assinaturaInfo|$assinaturasInfo, $logoSrc, $validationUrl
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

$assinaturasLista = [];
if (!empty($assinaturasInfo) && is_array($assinaturasInfo)) {
    foreach ($assinaturasInfo as $assinaturaItem) {
        if (!is_array($assinaturaItem)) {
            continue;
        }
        $assinaturasLista[] = [
            'nome' => (string)($assinaturaItem['nome'] ?? ''),
            'cargo' => (string)($assinaturaItem['cargo'] ?? ''),
            'data' => (string)($assinaturaItem['data'] ?? ''),
            'codigo_verificacao' => (string)($assinaturaItem['codigo_verificacao'] ?? ''),
        ];
    }
} elseif (!empty($assinaturaInfo) && is_array($assinaturaInfo)) {
    $assinaturasLista[] = [
        'nome' => (string)($assinaturaInfo['nome'] ?? ''),
        'cargo' => (string)($assinaturaInfo['cargo'] ?? ''),
        'data' => (string)($assinaturaInfo['data'] ?? ''),
        'codigo_verificacao' => (string)($assinaturaInfo['codigo_verificacao'] ?? ''),
    ];
}

$tipoExibicao = $tipoNome;
$tipoLower = strtolower($tipoNome);
if (strpos($tipoLower, 'memorando') !== false) {
    $tipoExibicao = 'Memorando';
} elseif (strpos($tipoLower, 'oficio') !== false || strpos($tipoLower, 'ofício') !== false) {
    $tipoExibicao = 'Ofício';
}

$validationUrl = trim((string)($validationUrl ?? ''));
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= h($tipoNome) ?> <?= h($numeroBruto) ?></title>
  <style>
    @page { margin: 4mm 10mm 6mm 16mm; }
    body {
      font-family: "Times New Roman", serif;
      color: #111;
      margin: 4mm 10mm 6mm 16mm;
      padding-bottom: 120px;
    }
    .header { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
    .header td { vertical-align: middle; }
    .header .logo-cell { width: 110px; padding-right: 18px; }
    .header img { height: 90px; }
    .header .org { text-transform: uppercase; font-size: 16px; line-height: 1.4; }
    .linha { width: 100%; border-collapse: collapse; margin: 18px 0 8px; }
    .linha td { vertical-align: top; }
    .linha .linha-titulo { padding-bottom: 10px; }
    .linha .linha-data { text-align: right; white-space: nowrap; padding-top: 10px; }
    .linha strong { font-weight: 700; }
    .body { margin: 28px 0 42px; font-size: 16px; line-height: 1.6; overflow-wrap: anywhere; }
    .body:after { content: ""; display: block; clear: both; }
    .body * { max-width: 100%; }
    .body table {
      width: 100% !important;
      max-width: 100% !important;
      border-collapse: collapse;
      margin: 8px 0;
      table-layout: fixed !important;
    }
    .body th, .body td {
      border: 1px solid #000;
      padding: 2px 4px;
      vertical-align: top;
      white-space: normal;
      word-break: break-word;
      overflow-wrap: anywhere;
      font-size: 10px !important;
      width: auto !important;
      min-width: 0 !important;
      max-width: 100% !important;
    }
    .body th { background: #f2f2f2; }
    .body colgroup col,
    .body col,
    .body tbody,
    .body tr {
      width: auto !important;
      min-width: 0 !important;
      max-width: 100% !important;
    }
    .body p, .body span, .body div { max-width: 100% !important; }
    .footer {
      width: 100%;
      border-collapse: collapse;
      margin-top: 56px;
      page-break-inside: avoid;
      clear: both;
    }
    .footer td { vertical-align: bottom; }
    .assinatura { text-align: center; }
    .assinatura .linha-ass { border-top: 1px solid #000; margin-top: 32px; padding-top: 6px; }
    .recebido { text-align: center; }
    .recebido .linha-rec { border-top: 1px solid #000; margin-top: 32px; padding-top: 6px; }
    .recebido-wrap { margin-top: 28px; }
    .meta { margin-top: 18px; margin-bottom: 22px; }
    .meta div { margin-bottom: 8px; }
    .meta div:last-child { margin-bottom: 0; }
    .meta .meta-assunto {
      margin-top: 16px;
      font-weight: 700;
      text-align: justify;
    }
    .rodape-institucional {
      position: fixed;
      left: 16mm;
      right: 10mm;
      bottom: 0;
      padding-top: 8px;
      border-top: 2px solid #000;
      text-align: center;
      page-break-inside: avoid;
      background: #fff;
    }
    .rodape-institucional .contato {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 11px;
      line-height: 1.2;
    }
    .assinatura .validacao {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 10px;
      line-height: 1.25;
      margin-top: 6px;
      word-break: break-word;
      overflow-wrap: anywhere;
    }
    .assinatura .validacao a {
      color: #111;
      text-decoration: underline;
    }
    .rodape-institucional .mensagem {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 18px;
      font-weight: 700;
      line-height: 1.15;
      margin-top: 2px;
    }
  </style>
</head>
<body>
  <div class="rodape-institucional">
    <div class="contato">Rua Cel. Júlio Pereira dos Santos, 465 - Fone: (55) 99663-5639 - E-mail: smecpedagogico.sa@gmail.com - CEP: 98.590-000 - Santo Augusto - RS</div>
    <div class="mensagem">“NÃO USE DROGAS, DOE ÓRGÃOS, DOE SANGUE: SALVE VIDAS”</div>
  </div>

  <table class="header">
    <tr>
      <td class="logo-cell">
        <?php if (!empty($logoSrc)): ?>
          <img src="<?= h($logoSrc) ?>" alt="Brasão">
        <?php endif; ?>
      </td>
      <td>
        <div class="org">
          Estado do Rio Grande do Sul<br>
          Município de Santo Augusto<br>
          Poder Executivo<br>
          Secretaria Municipal de Educação
        </div>
      </td>
    </tr>
  </table>

  <table class="linha">
    <tr>
      <td class="linha-titulo">
        <strong>
          <?= h($tipoExibicao) ?>
          <?php if ($de !== '—'): ?>
            <?= h($de) ?>
          <?php endif; ?>
          n° <?= h($numeroBruto) ?>
        </strong>
      </td>
      <td class="linha-data">Santo Augusto, <?= h($dataFormatada) ?></td>
    </tr>
  </table>

  <div class="meta">
    <div><strong>De:</strong> <?= h($de) ?></div>
    <div><strong>Para:</strong> <?= h(implode(', ', $para)) ?></div>
    <div class="meta-assunto"><strong>Assunto:</strong> <?= h($assunto) ?></div>
  </div>

  <div class="body">
    <?= $versao ? $versao['conteudo'] : '<em>Sem conteúdo.</em>' ?>
  </div>

  <?php if ($assinaturasLista !== []): ?>
    <table class="footer">
      <?php foreach (array_chunk($assinaturasLista, 2) as $assinaturasLinha): ?>
        <tr>
          <?php if (count($assinaturasLinha) === 1): ?>
            <td style="width:50%; padding-right:18px; padding-left:18px;"></td>
          <?php endif; ?>
          <?php foreach ($assinaturasLinha as $assinaturaItem): ?>
            <td style="width:50%; padding-right:18px; padding-left:18px;">
              <div class="assinatura">
                <div><strong>Assinatura</strong></div>
                <div><?= h($assinaturaItem['nome']) ?></div>
                <?php if ($assinaturaItem['cargo'] !== ''): ?><div><?= h($assinaturaItem['cargo']) ?></div><?php endif; ?>
                <?php if ($assinaturaItem['data'] !== ''): ?>
                  <div>
                    Assinado digitalmente em <?= h($assinaturaItem['data']) ?>.
                    <?php if (!empty($assinaturaItem['codigo_verificacao'])): ?>
                      Código de verificação: <?= h($assinaturaItem['codigo_verificacao']) ?>.
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <?php if ($validationUrl !== ''): ?>
                  <div class="validacao">
                    Validar assinatura:
                    <a href="<?= h($validationUrl) ?>"><?= h($validationUrl) ?></a>
                  </div>
                <?php endif; ?>
                <div class="linha-ass"></div>
              </div>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <div class="recebido-wrap">
    <table class="footer">
      <tr>
        <td style="width:50%;"></td>
        <td style="width:50%; padding-right:18px; padding-left:18px;">
          <div class="recebido">
            <div>Recebido ____/____/______</div>
            <div class="linha-rec"></div>
          </div>
        </td>
      </tr>
    </table>
  </div>

</body>
</html>
