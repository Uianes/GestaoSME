<div class="modal fade" id="ModalCadastrarPatrimonio" tabindex="-1" aria-labelledby="modalCadastrarLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5">Cadastrar Patrimônio</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="limparCampos()"></button>
      </div>
      <form action="./crud/insertPatrimonio.php" method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <h5 class="mb-3">Identificação do Bem</h5>
          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <label class="form-label" for="numeroPatrimonioCadastrar">Nº do Patrimônio</label>
              <input type="text" class="form-control" name="numeroPatrimonio" id="numeroPatrimonioCadastrar" oninput="atualizarContador(this, 'contadorNumeroPatrimonioCadastrar')" maxlength="44">
              <small class="form-text text-muted">Se não for informado um número de patrimônio, será gerado um número provisório automaticamente.</small>
              <div class="small text-muted mt-1" id="contadorNumeroPatrimonioCadastrar">0/44</div>
            </div>
            <div class="col-12">
              <label class="form-label" for="descricaoCadastrar">Descrição</label>
              <textarea class="form-control" name="descricao" id="descricaoCadastrar" rows="3" required></textarea>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="marcaCadastrar">Marca</label>
              <input type="text" class="form-control" name="marca" id="marcaCadastrar" maxlength="120">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="modeloCadastrar">Modelo</label>
              <input type="text" class="form-control" name="modelo" id="modeloCadastrar" maxlength="120">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="numeroSerieCadastrar">Número de série</label>
              <input type="text" class="form-control" name="numeroSerie" id="numeroSerieCadastrar" maxlength="120">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="corCadastrar">Cor</label>
              <input type="text" class="form-control" name="cor" id="corCadastrar" maxlength="80">
            </div>
          </div>

          <hr class="my-4">

          <h5 class="mb-3">Dados da Aquisição</h5>
          <div class="row g-3">
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="dataEntradaCadastrar">Data de entrada</label>
              <input type="date" class="form-control" name="dataEntrada" id="dataEntradaCadastrar" required>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="anoAquisicaoCadastrar">Ano da aquisição</label>
              <input type="number" class="form-control" name="anoAquisicao" id="anoAquisicaoCadastrar" min="1900" max="2100" step="1">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="nfeNumeroCadastrar">NFC-e / Nota fiscal</label>
              <input type="text" class="form-control" name="nfeNumero" id="nfeNumeroCadastrar" maxlength="60">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <label class="form-label" for="notaFiscalAnexoCadastrar">Anexo da nota fiscal</label>
              <input type="file" class="form-control" name="notaFiscalAnexo" id="notaFiscalAnexoCadastrar" accept=".pdf,image/*">
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="fornecedorNomeCadastrar">Nome do fornecedor</label>
              <input type="text" class="form-control" name="fornecedorNome" id="fornecedorNomeCadastrar" maxlength="180">
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="fornecedorCnpjCadastrar">CNPJ do fornecedor</label>
              <input type="text" class="form-control" name="fornecedorCnpj" id="fornecedorCnpjCadastrar" maxlength="18" placeholder="00.000.000/0000-00">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="valorUnitarioCadastrar">Valor unitário</label>
              <input type="number" class="form-control" name="valorUnitario" id="valorUnitarioCadastrar" min="0" step="0.01">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="valorTotalNotaCadastrar">Valor total da nota fiscal</label>
              <input type="number" class="form-control" name="valorTotalNota" id="valorTotalNotaCadastrar" min="0" step="0.01">
            </div>
          </div>

          <hr class="my-4">

          <h5 class="mb-3">Situação Atual</h5>
          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <label class="form-label" for="localizacaoCadastrar">Localização</label>
              <select name="localizacao" id="localizacaoCadastrar" class="form-select" required>
                <option selected disabled value="">Selecione uma opção</option>
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
              <label class="form-label" for="descricaoLocalizacaoCadastrar">Descrição da localização</label>
              <textarea class="form-control" name="descricaoLocalizacao" id="descricaoLocalizacaoCadastrar" oninput="atualizarContador(this, 'contadorDescricaoLocalizacaoCadastrar')" maxlength="500" rows="2" required></textarea>
              <div class="small text-muted mt-1" id="contadorDescricaoLocalizacaoCadastrar">0/500</div>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label d-block">Está em uso?</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="emUso" id="emUsoCadastrarSim" value="1">
                <label class="form-check-label" for="emUsoCadastrarSim">Sim</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="emUso" id="emUsoCadastrarNao" value="0">
                <label class="form-check-label" for="emUsoCadastrarNao">Não</label>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="estadoConservacaoCadastrar">Estado de conservação</label>
              <select id="estadoConservacaoCadastrar" name="estadoConservacao" class="form-select">
                <option value="">Selecione</option>
                <option value="Bom">Bom</option>
                <option value="Regular">Regular</option>
                <option value="Ruim">Ruim</option>
              </select>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="statusCadastrar">Status</label>
              <select id="statusCadastrar" name="status" class="form-select" onchange="toggleMemorando('statusCadastrar', 'memorandoCadastrar', 'contadorMemorandoCadastrar')" required>
                <option selected disabled value="">Selecione uma opção</option>
                <option value="Tombado">Tombado</option>
                <option value="Descarte">Descarte</option>
              </select>
            </div>
            <div class="col-12 col-lg-6">
              <label class="form-label" for="memorandoCadastrar">Memorando</label>
              <input id="memorandoCadastrar" type="text" class="form-control" name="memorando" oninput="atualizarContador(this, 'contadorMemorandoCadastrar')" maxlength="30" disabled>
              <div class="small text-muted mt-1" id="contadorMemorandoCadastrar">0/30</div>
            </div>
          </div>

          <hr class="my-4">

          <h5 class="mb-3">Origem da Aquisição</h5>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label d-block">Origem da aquisição</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="origemAquisicao" id="origemAquisicaoCadastrarAutonomia" value="Autonomia Financeira">
                <label class="form-check-label" for="origemAquisicaoCadastrarAutonomia">Autonomia Financeira</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="origemAquisicao" id="origemAquisicaoCadastrarPDDE" value="PDDE">
                <label class="form-check-label" for="origemAquisicaoCadastrarPDDE">PDDE</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="origemAquisicao" id="origemAquisicaoCadastrarCPM" value="CPM">
                <label class="form-check-label" for="origemAquisicaoCadastrarCPM">CPM</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="origemAquisicao" id="origemAquisicaoCadastrarMunicipio" value="Recursos do município">
                <label class="form-check-label" for="origemAquisicaoCadastrarMunicipio">Recursos do município</label>
              </div>
            </div>
          </div>

          <hr class="my-4">

          <h5 class="mb-3">Declaração de Registro</h5>
          <p class="mb-2">Declara que os bens acima relacionados encontram-se fisicamente na unidade escolar.</p>
          <div class="small text-muted">
            O sistema registrará automaticamente o responsável pelo cadastro, a data e hora do registro e a unidade vinculada no momento do lançamento.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal" onclick="limparCampos()">Cancelar</button>
          <input type="submit" class="btn btn-success" value="Cadastrar">
        </div>
      </form>
    </div>
  </div>
</div>
