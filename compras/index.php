<?php

declare(strict_types=1);

require __DIR__ . '/auth_guard.php';

compras_require_access();

?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Marketplace de Pesquisa de Preços</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body>
  <nav class="navbar navbar-expand-lg app-nav sticky-top">
    <div class="container-fluid">
      <div>
        <a class="navbar-brand fw-semibold" href="#">Pesquisa de Preços</a>
        <div class="nav-subtitle">Marketplace para composição de cesta e referências públicas</div>
      </div>
      <div class="ms-auto d-flex align-items-center gap-2">
        <span class="cart-pill" id="cartSummary">0 itens no carrinho</span>
        <button class="btn btn-primary btn-sm px-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartPanel">
          Ver carrinho
        </button>
      </div>
    </div>
  </nav>

  <main class="container-fluid py-4">
    <section class="search-shell mb-4">
      <div class="row g-3 align-items-end">
        <div class="col-xl-4 col-lg-6">
          <label class="form-label" for="searchInput">Buscar item</label>
          <input class="form-control form-control-lg" id="searchInput" type="search" placeholder="Ex.: notebook, cadeira, medicamento">
        </div>

        <div class="col-xl-2 col-lg-3 col-md-6">
          <label class="form-label mt-3" for="categorySelect">Categoria</label>
          <select class="form-select" id="categorySelect">
            <option value="">Todas</option>
          </select>
        </div>

        <div class="col-xl-2 col-lg-3 col-md-6">
          <label class="form-label mt-3" for="subcategorySelect">Subcategoria</label>
          <select class="form-select" id="subcategorySelect">
            <option value="">Todas</option>
          </select>
        </div>

        <div class="col-xl-2 col-lg-4 col-md-6">
          <label class="form-label" for="priceFilterSelect">Preço</label>
          <select class="form-select" id="priceFilterSelect">
            <option value="all" selected>Todos os preços</option>
            <option value="with">Com preço</option>
            <option value="without">Sem preço</option>
          </select>
        </div>

        <div class="col-xl-2 col-lg-4 col-md-6">
          <button class="btn btn-dark btn-lg w-100" id="searchButton" type="button">Pesquisar</button>
        </div>
      </div>

      <div class="filter-row mt-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <select class="form-select form-select-sm page-size" id="pageSizeSelect">
            <option value="24">24</option>
            <option value="48" selected>48</option>
            <option value="72">72</option>
            <option value="100">100</option>
          </select>
          <select class="form-select form-select-sm sort-select" id="sortSelect">
            <option value="recent" selected>Recentes</option>
            <option value="related">Mais relacionados</option>
          </select>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="onlyRelatedSwitch">
            <label class="form-check-label small" for="onlyRelatedSwitch">Com relacionados</label>
          </div>
        </div>
        <span class="text-secondary small" id="resultSummary"></span>
      </div>
    </section>

    <section>
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h5 mb-0">Itens disponíveis</h1>
      </div>
      <div class="table-shell">
        <table class="items-table">
          <thead>
            <tr>
              <th>Item</th>
              <th>Categoria</th>
              <th>Preço</th>
              <th>Órgão</th>
              <th>Relacionados</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="itemsGrid"></tbody>
        </table>
      </div>
      <nav class="pagination-bar mt-4" aria-label="Paginação">
        <button class="btn btn-outline-secondary btn-sm" id="prevPageButton" type="button">Anterior</button>
        <span class="text-secondary small" id="pageSummary"></span>
        <button class="btn btn-outline-secondary btn-sm" id="nextPageButton" type="button">Próxima</button>
      </nav>
    </section>
  </main>

  <div class="offcanvas offcanvas-end cart-canvas" tabindex="-1" id="cartPanel">
    <div class="offcanvas-header border-bottom">
      <h2 class="offcanvas-title h5">Carrinho da pesquisa</h2>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body">
      <div id="cartItems"></div>
      <form action="pdf.php" method="post" target="_blank" id="pdfForm">
        <input type="hidden" name="selection" id="pdfSelection">
        <button class="btn btn-primary w-100 mt-3" type="submit" id="pdfButton" disabled>Gerar PDF</button>
      </form>
      <form action="export_excel.php" method="post" target="_blank" id="excelForm">
        <input type="hidden" name="selection" id="excelSelection">
        <button class="btn btn-outline-success w-100 mt-2" type="submit" id="excelButton" disabled>Exportar Excel</button>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/app.js"></script>
</body>
</html>
