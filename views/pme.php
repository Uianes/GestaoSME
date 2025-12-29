<?php
require_once __DIR__ . '/../auth/session.php';
require_login();
$user = $_SESSION['user'] ?? null;
function h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-0 text-body-emphasis">Painel de monitoramento do Plano Municipal de Educação</h3>
    </div>
</div>
<form method="post" action="#" class="row g-3">
    <!-- Template para construir os quadros -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="fw-semibold mb-1">Meta 1:</div>
                <div class="text-muted small mb-3" style="text-align: justify">Universalizar, até 2016, a educação
                    infantil na pré-escola para as crianças de 4 (quatro) a 5 (cinco) anos de idade e ampliar a oferta
                    de educação infantil em creches de forma a atender, no mínimo, 50% (cinquenta por cento) das
                    crianças de até 3 (três) anos até o final da vigência deste PME. </div>
                <div class="row g-2 g-md-3">
                    <div class="col-6 col-md">
                        <input class="btn-check" type="radio" name="humor" id="mood<?= $i ?>"
                            value="<?= h($m['value']) ?>" required>
                        <label class="w-100 border rounded-3 p-3 text-center mood-card" for="mood<?= $i ?>">
                            <div class="display-6 mb-1"><i class="bi <?= h($m['icon']) ?>"></i></div>
                            <div class="small fw-semibold"><?= h($m['label']) ?></div>
                        </label>
                    </div>
                </div>
                <div data-selection="t/9514/n1/all/n6/4317806/v/allxp/p/all/c2/6794/c287/6557,6558,6559,6560,6561,6562,93071,93072,93073,93074,93075,93076,93077,93078,93079,93080,93081,93082,100362/c286/113635/l/v,p+c2+c287,t+c286"
                    class="sidra-widget-table"></div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="fw-semibold mb-1">Você sente que há apoio da equipe/gestão?</div>
                <div class="text-muted small mb-3">Selecione uma opção</div>
                <?php
                $apoio = [
                    ['value' => 'bastante', 'label' => 'Bastante'],
                    ['value' => 'um_pouco', 'label' => 'Um pouco'],
                    ['value' => 'nada', 'label' => 'Nada'],
                ];
                foreach ($apoio as $k => $op):
                    ?>
                    <div class="form-check py-2">
                        <input class="form-check-input" type="radio" name="apoio" id="apoio<?= $k ?>"
                            value="<?= h($op['value']) ?>" required>
                        <label class="form-check-label" for="apoio<?= $k ?>"><?= h($op['label']) ?></label>
                    </div>
                    <?php if ($k < 2): ?>
                        <hr class="my-1"><?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="fw-semibold mb-1">Qual a probabilidade de recomendar esta empresa?</div>
                <div class="text-muted small mb-3">0 = nada provável, 10 = muito provável</div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small">0</span>
                    <input type="range" class="form-range" min="0" max="10" step="1" name="recomendacao"
                        id="recomendacao" value="5">
                    <span class="text-muted small">10</span>
                    <span class="badge text-bg-secondary" id="recomendacaoLabel">5</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="fw-semibold mb-1">Como melhorar sua experiência de trabalho?</div>
                <div class="text-muted small mb-3">Conte com suas palavras (opcional)</div>
                <textarea class="form-control" name="melhoria" rows="4" placeholder="Escreva aqui..."></textarea>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body d-flex flex-column gap-2">
                <button class="btn btn-dark btn-lg w-100" type="submit">
                    <i class="bi bi-send me-2"></i>Enviar pesquisa
                </button>
                <div class="text-muted small text-center">
                    Seus dados podem ser tratados com confidencialidade (não compartilhe senhas).
                </div>
            </div>
        </div>
    </div>
</form>
<style>
    .mood-card {
        cursor: pointer;
    }

    .btn-check:checked+.mood-card {
        outline: 2px solid var(--bs-primary);
        border-color: var(--bs-primary) !important;
        background: var(--bs-primary-bg-subtle);
    }
</style>
<script>
    const range = document.getElementById('recomendacao');
    const label = document.getElementById('recomendacaoLabel');
    range.addEventListener('input', () => label.textContent = range.value);
</script>