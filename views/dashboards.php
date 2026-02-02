<?php
require_once __DIR__ . '/../auth/session.php';
require_login();

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h3 class="mb-1 text-body-emphasis">Dashboards</h3>
        <div class="text-muted">Escolha o painel que deseja visualizar.</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-md-6 col-xl-4">
        <a class="text-decoration-none" href="<?= h(url('app.php?page=avaliacoesExternas')) ?>">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <h5 class="card-title mb-1">Avaliações externas</h5>
                            <div class="text-muted">Resultados de aprendizagem e panorama das turmas.</div>
                        </div>
                        <i class="bi bi-graph-up fs-4 text-body-secondary"></i>
                    </div>
                    <div class="mt-3 text-primary fw-semibold">Abrir painel</div>
                </div>
            </div>
        </a>
    </div>
</div>
