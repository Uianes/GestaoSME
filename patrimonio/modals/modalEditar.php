<div class="modal fade" id="ModalEditarPatrimonio" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5">Editar Patrimônio</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="limparCampos()"></button>
      </div>
      <form action="./crud/updatePatrimonio.php" method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="numeroPatrimonioOriginal" id="numeroPatrimonioOriginalEditar">

          <h5 class="mb-3">Identificação do Bem</h5>
          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <label class="form-label" for="numeroPatrimonioEditar">Nº do Patrimônio</label>
              <input type="text" class="form-control" name="numeroPatrimonio" id="numeroPatrimonioEditar" maxlength="44">
              <div class="small text-warning mt-1 d-none" id="numeroPatrimonioProvisorioEditar">Este registro está usando um número provisório.</div>
            </div>
            <div class="col-12">
              <label class="form-label" for="descricaoEditar">Descrição</label>
              <textarea class="form-control" name="descricao" id="descricaoEditar" rows="3" required></textarea>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="marcaEditar">Marca</label>
              <input type="text" class="form-control" name="marca" id="marcaEditar" maxlength="120">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="modeloEditar">Modelo</label>
              <input type="text" class="form-control" name="modelo" id="modeloEditar" maxlength="120">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="numeroSerieEditar">Número de série</label>
              <input type="text" class="form-control" name="numeroSerie" id="numeroSerieEditar" maxlength="120">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="corEditar">Cor</label>
              <input type="text" class="form-control" name="cor" id="corEditar" maxlength="80">
            </div>
          </div>

          <hr class="my-4">

          <h5 class="mb-3">Dados da Aquisição</h5>
          <div class="row g-3">
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="dataEntradaEditar">Data de entrada</label>
              <input type="date" class="form-control" name="dataEntrada" id="dataEntradaEditar" required>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="anoAquisicaoEditar">Ano da aquisição</label>
              <input type="number" class="form-control" name="anoAquisicao" id="anoAquisicaoEditar" min="1900" max="2100" step="1">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="nfeNumeroEditar">NFC-e / Nota fiscal</label>
              <input type="text" class="form-control" name="nfeNumero" id="nfeNumeroEditar" maxlength="60">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="notaFiscalAnexoEditar">Atualizar anexo da nota fiscal</label>
              <input type="file" class="form-control" name="notaFiscalAnexo" id="notaFiscalAnexoEditar" accept=".pdf,image/*">
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="fornecedorNomeEditar">Nome do fornecedor</label>
              <input type="text" class="form-control" name="fornecedorNome" id="fornecedorNomeEditar" maxlength="180">
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="fornecedorCnpjEditar">CNPJ do fornecedor</label>
              <input type="text" class="form-control" name="fornecedorCnpj" id="fornecedorCnpjEditar" maxlength="18">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="valorUnitarioEditar">Valor unitário</label>
              <input type="number" class="form-control" name="valorUnitario" id="valorUnitarioEditar" min="0" step="0.01">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="valorTotalNotaEditar">Valor total da nota fiscal</label>
              <input type="number" class="form-control" name="valorTotalNota" id="valorTotalNotaEditar" min="0" step="0.01">
            </div>
          </div>

          <hr class="my-4">

          <h5 class="mb-3">Situação Atual</h5>
          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <label class="form-label" for="localizacaoEditar">Localização</label>
              <select name="localizacao" id="localizacaoEditar" class="form-select" required>
                <?php if (!empty($unidades)): ?>
                  <?php foreach ($unidades as $unidade): ?>
                    <option value="<?= (int)$unidade['id'] ?>"><?= htmlspecialchars($unidade['nome']) ?></option>
                  <?php endforeach; ?>
                <?php else: ?>
                  <option disabled value="">Nenhuma unidade encontrada</option>
                <?php endif; ?>
              </select>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="DescricaoLocalizacaoEditar">Descrição da localização</label>
              <textarea class="form-control" name="DescricaoLocalizacaoEditar" id="DescricaoLocalizacaoEditar" maxlength="500" rows="2" required></textarea>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label d-block">Está em uso?</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="emUso" id="emUsoEditarSim" value="1">
                <label class="form-check-label" for="emUsoEditarSim">Sim</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="emUso" id="emUsoEditarNao" value="0">
                <label class="form-check-label" for="emUsoEditarNao">Não</label>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="estadoConservacaoEditar">Estado de conservação</label>
              <select id="estadoConservacaoEditar" name="estadoConservacao" class="form-select">
                <option value="">Selecione</option>
                <option value="Bom">Bom</option>
                <option value="Regular">Regular</option>
                <option value="Ruim">Ruim</option>
              </select>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="statusEditar">Status</label>
              <input type="text" class="form-control" name="status" id="statusEditar" disabled>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="memorandoEditar">Memorando</label>
              <input id="memorandoEditar" type="text" class="form-control" name="memorando" maxlength="30" disabled>
            </div>
          </div>

          <hr class="my-4">

          <h5 class="mb-3">Origem da Aquisição</h5>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label d-block">Origem da aquisição</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="origemAquisicao" id="origemAquisicaoEditarAutonomia" value="Autonomia Financeira">
                <label class="form-check-label" for="origemAquisicaoEditarAutonomia">Autonomia Financeira</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="origemAquisicao" id="origemAquisicaoEditarPDDE" value="PDDE">
                <label class="form-check-label" for="origemAquisicaoEditarPDDE">PDDE</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="origemAquisicao" id="origemAquisicaoEditarCPM" value="CPM">
                <label class="form-check-label" for="origemAquisicaoEditarCPM">CPM</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="origemAquisicao" id="origemAquisicaoEditarMunicipio" value="Recursos do município">
                <label class="form-check-label" for="origemAquisicaoEditarMunicipio">Recursos do município</label>
              </div>
            </div>
          </div>

          <hr class="my-4">

          <h5 class="mb-3">Declaração de Registro</h5>
          <p class="mb-2">Declara que os bens acima relacionados encontram-se fisicamente na unidade escolar.</p>
          <div class="small text-muted">
            Os dados do responsável pelo cadastro, data e hora do registro e unidade vinculada são preservados automaticamente pelo sistema.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal" onclick="limparCampos()">Cancelar</button>
          <input type="submit" class="btn btn-success" value="Editar">
        </div>
      </form>
    </div>
  </div>
</div>
