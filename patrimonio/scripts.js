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
        if (input.type === 'submit' || input.id === 'statusDescarte' || input.type === 'hidden') {
            return;
        }
        if (input.type === 'checkbox' || input.type === 'radio') {
            input.checked = false;
        } else if (input.type === 'file') {
            input.value = '';
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

    const provisórioHint = document.getElementById('numeroPatrimonioProvisorioEditar');
    if (provisórioHint) {
      provisórioHint.classList.add('d-none');
    }
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

function normalizarDadosPatrimonio(buttonOrNumero, descricao, dataEntrada, localizacao, descLocalizacao, status, memorando) {
  if (buttonOrNumero && typeof buttonOrNumero === 'object' && buttonOrNumero.dataset) {
    return buttonOrNumero.dataset;
  }

  return {
    numeroOriginal: buttonOrNumero || '',
    numeroPatrimonio: buttonOrNumero || '',
    numeroProvisorio: '0',
    descricao: descricao || '',
    dataEntrada: dataEntrada || '',
    localizacao: localizacao || '',
    descLocalizacao: descLocalizacao || '',
    status: status || '',
    memorando: memorando || '',
    marca: '',
    modelo: '',
    numeroSerie: '',
    cor: '',
    anoAquisicao: '',
    nfeNumero: '',
    fornecedorNome: '',
    fornecedorCnpj: '',
    valorUnitario: '',
    valorTotalNota: '',
    emUso: '',
    estadoConservacao: '',
    origemAquisicao: '',
    notaFiscalAnexo: '',
    unidadeNome: ''
  };
}

function abrirModalEditar(buttonOrNumero, descricao, dataEntrada, localizacao, descLocalizacao, status, memorando) {
  const data = normalizarDadosPatrimonio(buttonOrNumero, descricao, dataEntrada, localizacao, descLocalizacao, status, memorando);
  document.getElementById('numeroPatrimonioOriginalEditar').value = data.numeroOriginal || '';
  document.getElementById('numeroPatrimonioEditar').value = data.numeroPatrimonio || '';
  document.getElementById('descricaoEditar').value = data.descricao || '';
  document.getElementById('dataEntradaEditar').value = data.dataEntrada || '';
  document.getElementById('localizacaoEditar').value = data.localizacao || '';
  document.getElementById('DescricaoLocalizacaoEditar').value = data.descLocalizacao || '';
  document.getElementById('statusEditar').value = data.status || '';
  document.getElementById('memorandoEditar').value = data.memorando || '';
  document.getElementById('marcaEditar').value = data.marca || '';
  document.getElementById('modeloEditar').value = data.modelo || '';
  document.getElementById('numeroSerieEditar').value = data.numeroSerie || '';
  document.getElementById('corEditar').value = data.cor || '';
  document.getElementById('anoAquisicaoEditar').value = data.anoAquisicao || '';
  document.getElementById('nfeNumeroEditar').value = data.nfeNumero || '';
  document.getElementById('fornecedorNomeEditar').value = data.fornecedorNome || '';
  document.getElementById('fornecedorCnpjEditar').value = data.fornecedorCnpj || '';
  document.getElementById('valorUnitarioEditar').value = data.valorUnitario || '';
  document.getElementById('valorTotalNotaEditar').value = data.valorTotalNota || '';
  document.getElementById('estadoConservacaoEditar').value = data.estadoConservacao || '';

  const origemAquisicao = data.origemAquisicao || '';
  const origemAutonomia = document.getElementById('origemAquisicaoEditarAutonomia');
  const origemPDDE = document.getElementById('origemAquisicaoEditarPDDE');
  const origemCPM = document.getElementById('origemAquisicaoEditarCPM');
  const origemMunicipio = document.getElementById('origemAquisicaoEditarMunicipio');
  if (origemAutonomia) origemAutonomia.checked = origemAquisicao === 'Autonomia Financeira';
  if (origemPDDE) origemPDDE.checked = origemAquisicao === 'PDDE';
  if (origemCPM) origemCPM.checked = origemAquisicao === 'CPM';
  if (origemMunicipio) origemMunicipio.checked = origemAquisicao === 'Recursos do município';

  const emUso = data.emUso;
  const emUsoSim = document.getElementById('emUsoEditarSim');
  const emUsoNao = document.getElementById('emUsoEditarNao');
  if (emUsoSim) emUsoSim.checked = emUso === '1';
  if (emUsoNao) emUsoNao.checked = emUso === '0';

  const provisórioHint = document.getElementById('numeroPatrimonioProvisorioEditar');
  if (provisórioHint) {
    provisórioHint.classList.toggle('d-none', data.numeroProvisorio !== '1');
  }

  let modalEditar = new bootstrap.Modal(document.getElementById('ModalEditarPatrimonio'));
  modalEditar.show();
}

function abrirModalVisualizar(buttonOrNumero, descricao, dataEntrada, localizacao, descLocalizacao, status, memorando) {
  const data = normalizarDadosPatrimonio(buttonOrNumero, descricao, dataEntrada, localizacao, descLocalizacao, status, memorando);

  const setText = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value || '-';
  };

  setText('viewNumeroPatrimonio', data.numeroPatrimonio || '');
  setText('viewDescricaoPatrimonio', data.descricao || '');
  setText('viewMarcaPatrimonio', data.marca || '');
  setText('viewModeloPatrimonio', data.modelo || '');
  setText('viewNumeroSeriePatrimonio', data.numeroSerie || '');
  setText('viewCorPatrimonio', data.cor || '');
  setText('viewDataEntradaPatrimonio', data.dataEntrada || '');
  setText('viewAnoAquisicaoPatrimonio', data.anoAquisicao || '');
  setText('viewNfeNumeroPatrimonio', data.nfeNumero || '');
  setText('viewFornecedorNomePatrimonio', data.fornecedorNome || '');
  setText('viewFornecedorCnpjPatrimonio', data.fornecedorCnpj || '');
  setText('viewValorUnitarioPatrimonio', data.valorUnitario || '');
  setText('viewValorTotalNotaPatrimonio', data.valorTotalNota || '');
  setText('viewLocalizacaoPatrimonio', data.unidadeNome || data.localizacao || '');
  setText('viewDescricaoLocalizacaoPatrimonio', data.descLocalizacao || '');
  setText('viewStatusPatrimonio', data.status || '');
  setText('viewMemorandoPatrimonio', data.memorando || '');
  setText('viewEmUsoPatrimonio', data.emUso === '1' ? 'Sim' : (data.emUso === '0' ? 'Não' : '-'));
  setText('viewEstadoConservacaoPatrimonio', data.estadoConservacao || '');
  setText('viewOrigemAquisicaoPatrimonio', data.origemAquisicao || '');

  const provisórioBadge = document.getElementById('viewNumeroProvisorioPatrimonio');
  if (provisórioBadge) {
    provisórioBadge.classList.toggle('d-none', data.numeroProvisorio !== '1');
  }

  const notaLink = document.getElementById('viewNotaFiscalLinkPatrimonio');
  const notaFrame = document.getElementById('viewNotaFiscalFramePatrimonio');
  const notaEmpty = document.getElementById('viewNotaFiscalEmptyPatrimonio');
  const notaPath = data.notaFiscalAnexo || '';
  const notaUrl = notaPath ? ('../' + notaPath.replace(/^\/+/, '')) : '';

  if (notaLink) {
    notaLink.href = notaUrl || '#';
    notaLink.classList.toggle('d-none', !notaUrl);
  }
  if (notaFrame) {
    notaFrame.src = notaUrl || 'about:blank';
    notaFrame.classList.toggle('d-none', !notaUrl);
  }
  if (notaEmpty) {
    notaEmpty.classList.toggle('d-none', !!notaUrl);
  }

  let modalVisualizar = new bootstrap.Modal(document.getElementById('ModalVisualizarPatrimonio'));
  modalVisualizar.show();
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
