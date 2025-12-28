<?php include 'componentes/head.php'; ?>
<?php
$isLogin = ($page ?? '') === 'login';
?>
<?php if ($isLogin): ?>
    <?php include $view; ?>
<?php else: ?>
    <div class="d-flex" style="min-height:100vh">
        <?php include 'componentes/sidebar.php'; ?>
        <div class="flex-grow-1 d-flex flex-column">
            <?php include 'componentes/nav.php'; ?>
            <!--
              Use Bootstrap theme-aware background so Dark Mode applies to the whole page.
              bg-light forces a light background even when data-bs-theme="dark".
            -->
            <main class="flex-grow-1 p-4 bg-body">
                <?php include $view; ?>
            </main>
        </div>
    </div>
<?php endif; ?>
<?php include 'componentes/scripts.php'; ?>