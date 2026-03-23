<?php
// layout.php
if (!isset($pageTitle)) $pageTitle = "Planejamento PPA";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        body {
            background: #f4f6f9;
        }
        .ppa-shell {
            min-height: 100vh;
        }
        .ppa-navbar {
            background: linear-gradient(135deg, #173d7a, #245fb2);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18);
        }
        .ppa-brand {
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .ppa-navbar .nav-link {
            color: rgba(255, 255, 255, 0.85);
        }
        .ppa-navbar .nav-link:hover,
        .ppa-navbar .nav-link.active {
            color: #fff;
        }
        .ppa-content {
            padding-bottom: 2rem;
        }
        .ppa-footer {
            background: #163560;
        }
    </style>
</head>
<body class="d-flex flex-column ppa-shell">

<nav class="navbar navbar-expand-lg navbar-dark ppa-navbar mb-4">
    <div class="container-fluid">
        <a class="navbar-brand ppa-brand" href="orgaos.php">Planejamento PPA</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="orgaos.php">Estrutura do PPA</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="painel_execucao.php">Painel de execução</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container ppa-content flex-grow-1">
    <?= $content ?? "" ?>
</main>

<footer class="ppa-footer text-white text-center py-3 mt-auto">
    <small>Sistema de Planejamento do PPA</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
