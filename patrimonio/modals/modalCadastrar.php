<div class="modal fade" id="ModalCadastrarPatrimonio" tabindex="-1" aria-labelledby="modalCadastrarLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5">Cadastrar Patrimônio</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="limparCampos()"></button>
      </div>
      <form action="./crud/insertPatrimonio.php" method="POST">
        <div class="modal-body">
          <div class="input-group">
            <span class="input-group-text">Nº Patrimônio</span>
            <input type="text" class="form-control" name="numeroPatrimonio" id="numeroPatrimonioCadastrar" oninput="atualizarContador(this, 'contadorNumeroPatrimonioCadastrar')" maxlength="44" required>
            <span class="input-group-text" id="contadorNumeroPatrimonioCadastrar">0/44</span>
          </div>
          <small class="form-text text-muted">Caso não tenha sido gerado um número de patrimônio, utilize o número da NF-e.</small>
          <div class="input-group my-3">
            <span class="input-group-text">Descrição</span>
            <textarea class="form-control" name="descricao" id="descricaoCadastrar" required></textarea>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Data Entrada</span>
            <input type="date" class="form-control" name="dataEntrada" required>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Localização</span>
            <select name="localizacao" class="form-select" required>
              <option selected disabled value="">Selecione uma Opção</option>
              <?php if (!empty($unidades)): ?>
                <?php foreach ($unidades as $unidade): ?>
                  <option value="<?= (int)$unidade['id'] ?>"><?= htmlspecialchars($unidade['nome']) ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <option disabled value="">Nenhuma unidade encontrada</option>
              <?php endif; ?>
            </select>
            <textarea class="form-control" name="descricaoLocalizacao" id="DescricaoLocalizacaoCadastrar" oninput="atualizarContador(this, 'contadorDescricaoLocalizacaoCadastrar')" maxlength="500" required></textarea>
            <span class="input-group-text" id="contadorDescricaoLocalizacaoCadastrar">0/500</span>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Status</span>
            <select id="statusCadastrar" name="status" class="form-select" onchange="toggleMemorando('statusCadastrar', 'memorandoCadastrar', 'contadorMemorandoCadastrar')" required>
              <option selected disabled value="">Selecione uma Opção</option>
              <option value="Tombado">Tombado</option>
              <option value="Descarte">Descarte</option>
            </select>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Memorando</span>
            <input id="memorandoCadastrar" type="text" class="form-control" name="memorando" oninput="atualizarContador(this, 'contadorMemorandoCadastrar')" maxlength="30" disabled>
            <span class="input-group-text" id="contadorMemorandoCadastrar">0/30</span>
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
