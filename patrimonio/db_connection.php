<?php
require_once __DIR__ . '/../config/config.php';

function open_connection(): mysqli
{
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if (!$conn instanceof mysqli) {
        throw new RuntimeException('Falha na conexão com o banco de dados: ' . mysqli_connect_error());
    }

    mysqli_set_charset($conn, 'utf8mb4');

    return $conn;
}

function close_connection($conn): void
{
    if ($conn instanceof mysqli) {
        mysqli_close($conn);
    }
}
