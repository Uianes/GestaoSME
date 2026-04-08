<?php
$docId = (int)($_GET['doc'] ?? 0);
$iframeUrl = url('protocolo/index.php' . ($docId > 0 ? '?doc=' . $docId : ''));
?>
<div class="bg-white border rounded shadow-sm" style="height: calc(100vh - 8rem);">
  <iframe
    title="Protocolo Eletrônico"
    src="<?= $iframeUrl ?>"
    style="width: 100%; height: 100%; border: 0;"
    loading="lazy"
  ></iframe>
</div>
