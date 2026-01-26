<?php
require_once __DIR__ . '/../auth/session.php';
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>

<div class="position-relative overflow-hidden">
    <div class="bg-bubble"></div>

    <div class="container py-5">
        <div class="row align-items-center g-5">
            <div class="col-12 col-lg-6">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="app-icon" aria-hidden="true">
                        <img src="img/logosmefundo.svg" alt="Logo SME">
                    </div>
                </div>

                <h1 class="display-4 hero-title mb-3">
                    <span style="color: rgba(17,24,39,.75);">Acesso ao sistema</span>
                </h1>

                <p class="fs-5" style="color: var(--text-2); max-width: 34rem;">
                    Entre com seu CPF, matrícula ou e-mail para acessar as funcionalidades internas da SME Santo Augusto.
                </p>

                <div class="mt-4 d-flex gap-2 flex-wrap">
                    <a class="btn btn-outline-secondary btn-lg rounded-4 px-4" href="index.php">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="mock-card">
                    <div class="mock-topbar">
                        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
                        <div class="mock-toolbar">
                            <span><i class="bi bi-lock"></i></span>
                            <span><i class="bi bi-person"></i></span>
                            <span><i class="bi bi-key"></i></span>
                        </div>
                    </div>

                    <div class="p-4 p-md-5">
                        <h4 class="mb-3">Entrar</h4>

                        <?php if ($error): ?>
                            <div class="alert alert-danger py-2 mb-3"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <form method="post" action="auth/login_handler.php" class="d-grid gap-3">
                            <div>
                                <label class="form-label mb-1">Usuário</label>
                                <input
                                    name="usuario"
                                    type="text"
                                    class="form-control form-control-lg rounded-4"
                                    placeholder="CPF, matrícula ou e-mail"
                                    autocomplete="username"
                                    required
                                >
                            </div>

                            <div>
                                <label class="form-label mb-1">Senha</label>
                                <input
                                    name="senha"
                                    type="password"
                                    class="form-control form-control-lg rounded-4"
                                    placeholder="Senha"
                                    autocomplete="current-password"
                                    required
                                >
                            </div>

                            <button class="btn btn-dark btn-lg rounded-4">
                                Entrar <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </form>

                        <div class="mt-3 text-muted small">
                            Se você ainda não tem acesso, solicite à administração da SME.
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3 text-muted small">
                    Desenvolvido por SME Santo Augusto
                </div>
            </div>
        </div>
    </div>
</div>