<?php

if (!function_exists('proto_signature_column_exists')) {
    function proto_signature_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $tableSafe = $conn->real_escape_string($table);
        $columnSafe = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('proto_govbr_schema_ready')) {
    function proto_govbr_schema_ready(mysqli $conn): bool
    {
        return proto_signature_column_exists($conn, 'doc_assinaturas', 'provedor')
            && proto_signature_column_exists($conn, 'doc_assinaturas', 'provedor_ref')
            && proto_signature_column_exists($conn, 'doc_assinaturas', 'certificado_publico')
            && proto_signature_column_exists($conn, 'doc_assinaturas', 'arquivo_assinatura')
            && proto_signature_column_exists($conn, 'doc_assinaturas', 'assinatura_mime');
    }
}

if (!function_exists('proto_govbr_signing_enabled')) {
    function proto_govbr_signing_enabled(): bool
    {
        return (bool)(defined('GOVBR_SIGN_ENABLED') ? GOVBR_SIGN_ENABLED : false)
            && trim((string)(defined('GOVBR_SIGN_CLIENT_ID') ? GOVBR_SIGN_CLIENT_ID : '')) !== ''
            && trim((string)(defined('GOVBR_SIGN_CLIENT_SECRET') ? GOVBR_SIGN_CLIENT_SECRET : '')) !== ''
            && trim((string)(defined('GOVBR_SIGN_REDIRECT_URI') ? GOVBR_SIGN_REDIRECT_URI : '')) !== '';
    }
}

if (!function_exists('proto_govbr_sign_env')) {
    function proto_govbr_sign_env(): string
    {
        $env = strtolower(trim((string)(defined('GOVBR_SIGN_ENV') ? GOVBR_SIGN_ENV : 'staging')));
        return $env !== '' ? $env : 'staging';
    }
}

if (!function_exists('proto_govbr_default_authorize_url')) {
    function proto_govbr_default_authorize_url(): string
    {
        return 'https://cas.staging.iti.br/oauth2.0/authorize';
    }
}

if (!function_exists('proto_govbr_default_token_url')) {
    function proto_govbr_default_token_url(): string
    {
        return 'https://cas.staging.iti.br/oauth2.0/token';
    }
}

if (!function_exists('proto_govbr_default_sign_url')) {
    function proto_govbr_default_sign_url(): string
    {
        return 'https://assinatura-api.staging.iti.br/externo/v2/assinarPKCS7';
    }
}

if (!function_exists('proto_govbr_default_certificate_url')) {
    function proto_govbr_default_certificate_url(): string
    {
        return 'https://assinatura-api.staging.iti.br/externo/v2/certificadoPublico';
    }
}

if (!function_exists('proto_govbr_validation_url')) {
    function proto_govbr_validation_url(): string
    {
        return proto_govbr_sign_env() === 'production'
            ? 'https://validar.iti.gov.br'
            : 'https://h-validar.iti.gov.br/index.html';
    }
}

if (!function_exists('proto_govbr_authorize_url')) {
    function proto_govbr_authorize_url(): string
    {
        $configured = trim((string)(defined('GOVBR_SIGN_AUTHORIZE_URL') ? GOVBR_SIGN_AUTHORIZE_URL : ''));
        return $configured !== '' ? $configured : proto_govbr_default_authorize_url();
    }
}

if (!function_exists('proto_govbr_token_url')) {
    function proto_govbr_token_url(): string
    {
        $configured = trim((string)(defined('GOVBR_SIGN_TOKEN_URL') ? GOVBR_SIGN_TOKEN_URL : ''));
        return $configured !== '' ? $configured : proto_govbr_default_token_url();
    }
}

if (!function_exists('proto_govbr_sign_url')) {
    function proto_govbr_sign_url(): string
    {
        $configured = trim((string)(defined('GOVBR_SIGN_SIGN_URL') ? GOVBR_SIGN_SIGN_URL : ''));
        return $configured !== '' ? $configured : proto_govbr_default_sign_url();
    }
}

if (!function_exists('proto_govbr_certificate_url')) {
    function proto_govbr_certificate_url(): string
    {
        $configured = trim((string)(defined('GOVBR_SIGN_CERT_URL') ? GOVBR_SIGN_CERT_URL : ''));
        return $configured !== '' ? $configured : proto_govbr_default_certificate_url();
    }
}

if (!function_exists('proto_govbr_build_authorization_url')) {
    function proto_govbr_build_authorization_url(int $documentoId, int $assinaturaId): string
    {
        if (!proto_govbr_signing_enabled()) {
            throw new RuntimeException('Integração GOV.BR não configurada.');
        }

        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['proto_govbr_sign'] = [
            'state' => $state,
            'nonce' => $nonce,
            'documento_id' => $documentoId,
            'assinatura_id' => $assinaturaId,
            'created_at' => time(),
        ];

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => GOVBR_SIGN_CLIENT_ID,
            'scope' => trim((string)(defined('GOVBR_SIGN_SCOPE') ? GOVBR_SIGN_SCOPE : 'sign')),
            'redirect_uri' => GOVBR_SIGN_REDIRECT_URI,
            'state' => $state,
            'nonce' => $nonce,
        ], '', '&', PHP_QUERY_RFC3986);

        return proto_govbr_authorize_url() . '?' . $query;
    }
}

if (!function_exists('proto_govbr_consume_session')) {
    function proto_govbr_consume_session(string $state): array
    {
        $payload = $_SESSION['proto_govbr_sign'] ?? null;
        unset($_SESSION['proto_govbr_sign']);

        if (!is_array($payload)) {
            throw new RuntimeException('Sessão de assinatura GOV.BR não encontrada.');
        }
        if (($payload['state'] ?? '') !== $state) {
            throw new RuntimeException('Retorno GOV.BR inválido: state não confere.');
        }
        if ((int)($payload['created_at'] ?? 0) < (time() - 1200)) {
            throw new RuntimeException('Sessão GOV.BR expirada. Inicie a assinatura novamente.');
        }

        return $payload;
    }
}

if (!function_exists('proto_govbr_http_request')) {
    function proto_govbr_http_request(string $method, string $url, array|string|null $body = null, array $headers = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('A extensão cURL é obrigatória para a integração GOV.BR.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Não foi possível iniciar a requisição GOV.BR.');
        }

        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                $normalizedHeaders[] = (string)$value;
                continue;
            }
            $normalizedHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $normalizedHeaders,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException('Falha na comunicação com o GOV.BR: ' . $error);
        }

        return [
            'status' => $statusCode,
            'body' => $responseBody,
        ];
    }
}

if (!function_exists('proto_govbr_exchange_code_for_token')) {
    function proto_govbr_exchange_code_for_token(string $code): array
    {
        $response = proto_govbr_http_request('POST', proto_govbr_token_url(), http_build_query([
            'code' => $code,
            'client_id' => GOVBR_SIGN_CLIENT_ID,
            'grant_type' => 'authorization_code',
            'client_secret' => GOVBR_SIGN_CLIENT_SECRET,
            'redirect_uri' => GOVBR_SIGN_REDIRECT_URI,
        ], '', '&', PHP_QUERY_RFC3986), [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ]);

        $data = json_decode((string)$response['body'], true);
        if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($data) || empty($data['access_token'])) {
            $message = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : trim((string)$response['body']);
            throw new RuntimeException('Falha ao obter token GOV.BR: ' . $message);
        }

        return $data;
    }
}

if (!function_exists('proto_govbr_fetch_certificate')) {
    function proto_govbr_fetch_certificate(string $accessToken): string
    {
        $response = proto_govbr_http_request('GET', proto_govbr_certificate_url(), null, [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('Falha ao obter certificado GOV.BR: ' . trim((string)$response['body']));
        }

        return trim((string)$response['body']);
    }
}

if (!function_exists('proto_govbr_sign_hash')) {
    function proto_govbr_sign_hash(string $accessToken, string $hashBase64): string
    {
        $response = proto_govbr_http_request('POST', proto_govbr_sign_url(), json_encode([
            'hashBase64' => $hashBase64,
        ], JSON_UNESCAPED_SLASHES), [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('Falha ao assinar com GOV.BR: ' . trim((string)$response['body']));
        }

        return (string)$response['body'];
    }
}

if (!function_exists('proto_govbr_signature_storage_relpath')) {
    function proto_govbr_signature_storage_relpath(int $documentoId, int $assinaturaId, string $extension): string
    {
        $extension = ltrim($extension, '.');
        return 'uploads/docs/assinaturas_govbr/' . $documentoId . '/assinatura-' . $assinaturaId . '.' . $extension;
    }
}

if (!function_exists('proto_govbr_store_signature_file')) {
    function proto_govbr_store_signature_file(int $documentoId, int $assinaturaId, string $extension, string $contents): string
    {
        $relativePath = proto_govbr_signature_storage_relpath($documentoId, $assinaturaId, $extension);
        $absolutePath = dirname(__DIR__) . '/' . $relativePath;
        $dir = dirname($absolutePath);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar a pasta de assinatura GOV.BR.');
        }

        if (file_put_contents($absolutePath, $contents) === false) {
            throw new RuntimeException('Não foi possível salvar o arquivo da assinatura GOV.BR.');
        }

        return $relativePath;
    }
}
