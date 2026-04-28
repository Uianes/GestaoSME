<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function compras_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('COMPRAS_DB_HOST') ?: (defined('COMPRAS_DB_HOST') ? COMPRAS_DB_HOST : DB_HOST);
    $port = getenv('COMPRAS_DB_PORT') ?: (defined('COMPRAS_DB_PORT') ? COMPRAS_DB_PORT : DB_PORT);
    $database = getenv('COMPRAS_DB_NAME') ?: (defined('COMPRAS_DB_NAME') ? COMPRAS_DB_NAME : 'compras');
    $user = getenv('COMPRAS_DB_USER') ?: (defined('COMPRAS_DB_USER') ? COMPRAS_DB_USER : DB_USER);
    $password = getenv('COMPRAS_DB_PASS') ?: (defined('COMPRAS_DB_PASS') ? COMPRAS_DB_PASS : DB_PASS);

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function money_value(?string $value): ?float
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $normalized = str_replace(',', '.', trim($value));
    return is_numeric($normalized) ? (float) $normalized : null;
}

function format_money(?float $value): string
{
    if ($value === null) {
        return 'Não informado';
    }

    return 'R$ ' . number_format($value, 2, ',', '.');
}
