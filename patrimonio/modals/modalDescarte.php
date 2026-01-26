<div class="modal fade" id="ModalDescartePatrimonio" tabindex="-1" aria-labelledby="modalDescarteLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5">Descarte Patrimônio</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="limparCampos()"></button>
      </div>
      <form action="./crud/descartePatrimonio.php" method="POST">
        <div class="modal-body">
          <div class="input-group mb-3">
            <span class="input-group-text">Nº Patrimônio</span>
            <input type="text" class="form-control" name="numeroPatrimonio" id="numeroPatrimonioDescarte" readonly>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Descrição</span>
            <textarea class="form-control" name="descricao" id="descricaoDescarte" disabled></textarea>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Data Entrada</span>
            <input type="date" class="form-control" name="dataEntrada" id="dataEntradaDescarte" disabled>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Localização</span>
            <select name="localizacao" id="localizacaoDescarte" class="form-select" required>
              <?php if (!empty($unidades)): ?>
                <?php foreach ($unidades as $unidade): ?>
                  <option value="<?= (int)$unidade['id'] ?>"><?= htmlspecialchars($unidade['nome']) ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <option disabled value="">Nenhuma unidade encontrada</option>
              <?php endif; ?>
            </select>
            <textarea class="form-control" name="descricaoLocalizacao" id="DescricaoLocalizacaoDescarte" maxlength="500" required></textarea>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Status</span>
            <input type="text" id="statusDescarte" value="Descarte" class="form-control" disabled>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Memorando</span>
            <input id="memorandoDescarte" type="text" class="form-control" name="memorando" oninput="atualizarContador(this, 'contadorMemorandoDescarte')" maxlength="30" required>
            <span class="input-group-text" id="contadorMemorandoDescarte">0/30</span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal" onclick="limparCampos()">Cancelar</button>
          <input type="submit" class="btn btn-success">
        </div>
      </form>
    </div>
  </div>
</div>
