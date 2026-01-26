<div class="modal fade" id="ModalGerarPDF" tabindex="-1" aria-labelledby="ModalEscolasLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ModalEscolasLabel">Selecione as Escolas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="limparCampos()"></button>
      </div>
      <div class="modal-body">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="checkAll" onclick="toggleAllCheckboxes(this)">
          <label class="form-check-label" for="checkAll">Selecionar Todas</label>
        </div>
        <form action="pdf.php" method="POST" onsubmit="return validarCheck()" target="_blank">
          <?php if (!empty($unidades)): ?>
            <?php foreach ($unidades as $unidade): ?>
              <div class="form-check">
                <input class="form-check-input checkSchool" type="checkbox" name="locais[]" value="<?= (int)$unidade['id'] ?>" onclick="uncheckMainCheckbox()">
                <label class="form-check-label"><?= htmlspecialchars($unidade['nome']) ?></label>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-muted">Nenhuma unidade encontrada.</div>
          <?php endif; ?>
          <div class="mt-3">
            <button type="submit" class="btn btn-primary"">Gerar PDF</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
