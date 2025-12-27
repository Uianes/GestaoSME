<?php include 'componentes/head.php'; ?>

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

                <h1 class="display-3 hero-title mb-4">
                    <span style="color: rgba(17,24,39,.75);">SME Santo Augusto</span>
                </h1>

                <p class="fs-4" style="color: var(--text-2); max-width: 34rem;">
                    Site oficial da Secretaria Municipal de Educação de Santo Augusto - RS, para gestão de sistemas
                    internos.
                </p>

                <div class="mt-4 d-flex gap-2 flex-wrap">
                    <a class="btn btn-dark btn-lg rounded-4 px-4" href="app.php?page=login">
                        Entrar <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                    <a class="btn btn-outline-dark btn-lg rounded-4 px-4" href="#">
                        Cadastrar
                    </a>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="mock-card">
                    <div class="mock-topbar">
                        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
                        <div class="mock-toolbar">
                            <span><i class="bi bi-cursor"></i></span>
                            <span><i class="bi bi-bounding-box"></i></span>
                            <span><i class="bi bi-type"></i></span>
                            <span><i class="bi bi-hand-index-thumb"></i></span>
                        </div>
                    </div>

                    <div class="mock-body">
                        <aside class="mock-sidebar">
                            <div class="mock-section-title">
                                <span>Menu</span>
                                <span class="text-muted">+</span>
                            </div>

                            <div class="d-grid gap-1">
                                <div class="mock-item active">
                                    <span class="bullet" style="background: rgba(122,92,255,.65)"></span>
                                    <span>Certificados</span>
                                </div>
                                <div class="mock-item">
                                    <span class="bullet"></span>
                                    <span>Gestão de Documentos</span>
                                </div>
                                <div class="mock-item">
                                    <span class="bullet"></span>
                                    <span>Protocolo</span>
                                </div>
                                <div class="mock-item">
                                    <span class="bullet"></span>
                                    <span>Cursos</span>
                                </div>
                                <div class="mock-item">
                                    <span class="bullet"></span>
                                    <span>Dashboards</span>
                                </div>
                            </div>

                            <hr class="my-3">

                            <div class="mock-section-title">
                                <span>Conteúdos</span>
                            </div>
                            <div class="text-muted small">
                                Sistemas próprios para gestão escolar.
                            </div>
                        </aside>

                        <main class="mock-canvas">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="pill"><i class="bi bi-bookmark-fill"></i> Certificados</span>
                                <span class="pill">Documentos</span>
                                <span class="pill">Protocolo</span>
                            </div>

                            <div class="big-list">
                                <div class="rowi active">
                                    <div class="emoji">📕</div>
                                    <div>Certificados</div>
                                </div>
                                <div class="rowi">
                                    <div class="emoji">⚙️</div>
                                    <div>Gestão de Documentos</div>
                                </div>
                                <div class="rowi">
                                    <div class="emoji">🛠️</div>
                                    <div>Protocolo</div>
                                </div>
                                <div class="rowi">
                                    <div class="emoji">🧪</div>
                                    <div>Cursos</div>
                                </div>
                            </div>
                        </main>
                    </div>

                </div>
                <div class="text-center mt-3 text-muted small">
                    Desenvolvido por SME Santo Augusto
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'componentes/scripts.php'; ?>