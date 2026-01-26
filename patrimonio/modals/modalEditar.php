<div class="modal fade" id="ModalEditarPatrimonio" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5">Editar Patrimônio</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="limparCampos()"></button>
      </div>
      <form action="./crud/updatePatrimonio.php" method="POST">
        <div class="modal-body">
          <div class="input-group mb-3">
            <span class="input-group-text">Nº Patrimônio</span>
            <input type="text" class="form-control" name="numeroPatrimonio" id="numeroPatrimonioEditar" readonly>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Descrição</span>
            <textarea class="form-control" name="descricao" id="descricaoEditar" required></textarea>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Data Entrada</span>
            <input type="date" class="form-control" name="dataEntrada" id="dataEntradaEditar" required>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Localização</span>
            <select name="localizacao" id="localizacaoEditar" class="form-select" required>
              <?php if (!empty($unidades)): ?>
                <?php foreach ($unidades as $unidade): ?>
                  <option value="<?= (int)$unidade['id'] ?>"><?= htmlspecialchars($unidade['nome']) ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <option disabled value="">Nenhuma unidade encontrada</option>
              <?php endif; ?>
            </select>
            <textarea class="form-control" name="DescricaoLocalizacaoEditar" id="DescricaoLocalizacaoEditar" maxlength="500" required></textarea>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Status</span>
            <input type="text" class="form-control" name="status" id="statusEditar" disabled>
          </div>
          <div class="input-group mb-3">
            <span class="input-group-text">Memorando</span>
            <input id="memorandoEditar" type="text" class="form-control" name="memorando" maxlength="30" disabled>
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
