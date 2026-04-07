<div class="modal fade" id="ModalVisualizarPatrimonio" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5">Visualizar Patrimônio</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h5 class="mb-3">Identificação do Bem</h5>
        <div class="row g-3">
          <div class="col-12 col-lg-6">
            <div class="text-muted small">Nº do Patrimônio</div>
            <div class="fw-semibold" id="viewNumeroPatrimonio">-</div>
            <div class="mt-1">
              <span class="badge text-bg-warning d-none" id="viewNumeroProvisorioPatrimonio">Provisório</span>
            </div>
          </div>
          <div class="col-12">
            <div class="text-muted small">Descrição</div>
            <div id="viewDescricaoPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-6 col-lg-3">
            <div class="text-muted small">Marca</div>
            <div id="viewMarcaPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-6 col-lg-3">
            <div class="text-muted small">Modelo</div>
            <div id="viewModeloPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-6 col-lg-3">
            <div class="text-muted small">Número de série</div>
            <div id="viewNumeroSeriePatrimonio">-</div>
          </div>
          <div class="col-12 col-md-6 col-lg-3">
            <div class="text-muted small">Cor</div>
            <div id="viewCorPatrimonio">-</div>
          </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">Dados da Aquisição</h5>
        <div class="row g-3">
          <div class="col-12 col-md-6 col-lg-3">
            <div class="text-muted small">Data de entrada</div>
            <div id="viewDataEntradaPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-6 col-lg-3">
            <div class="text-muted small">Ano da aquisição</div>
            <div id="viewAnoAquisicaoPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-6 col-lg-3">
            <div class="text-muted small">NFC-e / Nota fiscal</div>
            <div id="viewNfeNumeroPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-6 col-lg-3">
            <div class="text-muted small">Valor unitário</div>
            <div id="viewValorUnitarioPatrimonio">-</div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="text-muted small">Nome do fornecedor</div>
            <div id="viewFornecedorNomePatrimonio">-</div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="text-muted small">CNPJ do fornecedor</div>
            <div id="viewFornecedorCnpjPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-muted small">Valor total da nota</div>
            <div id="viewValorTotalNotaPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-muted small">Anexo da nota fiscal</div>
            <div class="d-flex flex-column gap-2">
              <a class="btn btn-sm btn-outline-primary d-none align-self-start" id="viewNotaFiscalLinkPatrimonio" href="#" target="_blank">Abrir anexo</a>
              <div class="text-muted" id="viewNotaFiscalEmptyPatrimonio">Nenhum anexo enviado.</div>
            </div>
          </div>
          <div class="col-12">
            <iframe
              id="viewNotaFiscalFramePatrimonio"
              title="Visualização da nota fiscal"
              class="w-100 d-none border rounded"
              style="min-height: 420px;"
            ></iframe>
          </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">Origem da Aquisição</h5>
        <div class="row g-3">
          <div class="col-12">
            <div class="text-muted small">Origem da aquisição</div>
            <div id="viewOrigemAquisicaoPatrimonio">-</div>
          </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">Situação Atual</h5>
        <div class="row g-3">
          <div class="col-12 col-md-6 col-lg-4">
            <div class="text-muted small">Localização</div>
            <div id="viewLocalizacaoPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="text-muted small">Está em uso?</div>
            <div id="viewEmUsoPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="text-muted small">Estado de conservação</div>
            <div id="viewEstadoConservacaoPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-muted small">Descrição da localização</div>
            <div id="viewDescricaoLocalizacaoPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="text-muted small">Status</div>
            <div id="viewStatusPatrimonio">-</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="text-muted small">Memorando</div>
            <div id="viewMemorandoPatrimonio">-</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>
