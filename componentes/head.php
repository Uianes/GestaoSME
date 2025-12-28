<?php
require_once __DIR__ . '/../routes.php';
$bodyClass = $bodyClass ?? '';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SME Santo Augusto</title>

  <!-- Dark/Light mode (Bootstrap 5.3): aplica o tema ANTES do CSS para evitar "flash" -->
  <script>
  (function () {
    const KEY = 'sa_theme';

    function getCookie(name) {
      const m = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
      return m ? decodeURIComponent(m[2]) : null;
    }

    const saved = localStorage.getItem(KEY) || getCookie(KEY) || 'light';
    const effective = saved === 'system'
      ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
      : saved;

    // aplica no <html>
    document.documentElement.setAttribute('data-bs-theme', effective);
    // e também no <body> (quando existir)
    document.addEventListener('DOMContentLoaded', function () {
      if (document.body) document.body.setAttribute('data-bs-theme', effective);
    });
  })();
  </script>

  <link rel="icon" href="<?= asset('img/icone.ico') ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= asset('style.css') ?>">
  <link rel="stylesheet" href="<?= asset('componentes/sidebars.css') ?>">
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="light">