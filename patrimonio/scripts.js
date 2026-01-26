document.addEventListener('DOMContentLoaded', function () {
  const toastElement = document.getElementById('toastMessage');
  if (toastElement) {
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
  }
});

function limparCampos() {
    const inputs = document.querySelectorAll('input, select, textarea');

    inputs.forEach(input => {
        if (input.type === 'submit' || input.id === 'statusDescarte') {
            return;
        }
        if (input.type === 'checkbox') {
            input.checked = false;
        } else {
            input.value = '';
        }
    });

    const contadores = {
        "contadorNumeroPatrimonioCadastrar": "44",
        "contadorDescricaoLocalizacaoCadastrar": "500",
        "contadorMemorandoCadastrar": "30",
        "contadorMemorandoDescarte": "30"
    };

    Object.keys(contadores).forEach(id => {
        const contador = document.getElementById(id);
        if (contador) {
            contador.innerText = `0/${contadores[id]}`;
        }
    });
}

function atualizarContador(input, contadorId) {
    const contador = document.getElementById(contadorId);
    const maxLength = input.maxLength;
    contador.innerText = `${input.value.length}/${maxLength}`;
}

function toggleMemorando(selectId, memorandoId, contadorMemorandoID) {
    const status = document.getElementById(selectId);
    const memorando = document.getElementById(memorandoId);

    if (status.value == "Descarte") {
        memorando.disabled = false;
        memorando.required = true;
    } else if (status.value == "Tombado") {
        memorando.disabled = true;
        memorando.required = false;
        memorando.value = '';
        atualizarContador(memorando, contadorMemorandoID);
    }
}

function abrirModalEditar(numPatrimonio, descricao, dataEntrada, localizacao, descLocalizacao, status, memorando) {
  document.getElementById('numeroPatrimonioEditar').value = numPatrimonio;
  document.getElementById('descricaoEditar').value = descricao;
  document.getElementById('dataEntradaEditar').value = dataEntrada;
  document.getElementById('localizacaoEditar').value = localizacao;
  document.getElementById('DescricaoLocalizacaoEditar').value = descLocalizacao;
  document.getElementById('statusEditar').value = status;
  document.getElementById('memorandoEditar').value = memorando;

  let modalEditar = new bootstrap.Modal(document.getElementById('ModalEditarPatrimonio'));
  modalEditar.show();
}

function abrirModalExcluir(numPatrimonio, descricao, dataEntrada, localizacao, descLocalizacao, status, memorando) {
  document.getElementById('numeroPatrimonioExcluir').value = numPatrimonio;
  document.getElementById('descricaoExcluir').value = descricao;
  document.getElementById('dataEntradaExcluir').value = dataEntrada;
  document.getElementById('localizacaoExcluir').value = localizacao;
  document.getElementById('descricaoLocalizacaoExcluir').value = descLocalizacao;
  document.getElementById('statusExcluir').value = status;
  document.getElementById('memorandoExcluir').value = memorando;

  let modalExcluir = new bootstrap.Modal(document.getElementById('ModalExcluirPatrimonio'));
  modalExcluir.show();
}

function abrirModalDescarte(numPatrimonio, descricao, dataEntrada, localizacao, descLocalizacao, memorando) {
    document.getElementById('numeroPatrimonioDescarte').value = numPatrimonio;
    document.getElementById('descricaoDescarte').value = descricao;
    document.getElementById('dataEntradaDescarte').value = dataEntrada;
    document.getElementById('localizacaoDescarte').value = localizacao;
    document.getElementById('DescricaoLocalizacaoDescarte').value = descLocalizacao;
    document.getElementById('memorandoDescarte').value = memorando;

    let modalDescarte = new bootstrap.Modal(document.getElementById('ModalDescartePatrimonio'));
    modalDescarte.show();
}

function toggleAllCheckboxes(mainCheckbox) {
  const checkboxes = document.querySelectorAll('.checkSchool');
  checkboxes.forEach(cb => cb.checked = mainCheckbox.checked);
}

function uncheckMainCheckbox() {
  const mainCheckbox = document.getElementById('checkAll');
  if (!this.checked) mainCheckbox.checked = false;
}

function validarCheck(){
    const checkboxes = document.querySelectorAll('.checkSchool');
    let valid = false;
    for(const c of checkboxes){
        if(c.checked) {
            valid = true;
            break;
        }
    }
    if(valid){
        const modalEl = document.getElementById('ModalGerarPDF');
        let modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(modalEl);
        }

        modalEl.addEventListener('hidden.bs.modal', function() {
            limparCampos();
        }, {once: true});

        modalInstance.hide();
        return true;
    }
    alert('Selecione pelo menos um local.');
    return false;
}