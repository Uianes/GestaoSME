<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/permissions.php';

function compras_require_access(bool $json = false): void
{
    if (!is_logged_in()) {
        if (!$json) {
            require_login();
        }

        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Login obrigatório.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (user_can_access_system('compras')) {
        return;
    }

    http_response_code(403);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Sem permissão de acesso.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo 'Sem permissão de acesso.';
    }
    exit;
}
