<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/permissions.php';

if (!user_can_access_system('planejamento')) {
    http_response_code(403);
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Planejamento PPA</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    </head>
    <body class="bg-light">
        <div class="container py-4">
            <div class="alert alert-danger mb-0">Sem permissão de acesso.</div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$host = defined('PLANEJAMENTO_DB_HOST') ? PLANEJAMENTO_DB_HOST : DB_HOST;
$port = defined('PLANEJAMENTO_DB_PORT') ? PLANEJAMENTO_DB_PORT : DB_PORT;
$db = defined('PLANEJAMENTO_DB_NAME') ? PLANEJAMENTO_DB_NAME : 'u569083206_planejamento';
$user = defined('PLANEJAMENTO_DB_USER') ? PLANEJAMENTO_DB_USER : DB_USER;
$pass = defined('PLANEJAMENTO_DB_PASS') ? PLANEJAMENTO_DB_PASS : DB_PASS;
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Planejamento PPA</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    </head>
    <body class="bg-light">
        <div class="container py-4">
            <div class="alert alert-danger mb-0">
                Nao foi possivel conectar ao banco de dados do modulo Planejamento.
                Verifique a base <code><?= htmlspecialchars($db, ENT_QUOTES, 'UTF-8') ?></code>.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
