<?php

if (!function_exists('proto_signature_verification_code')) {
    function proto_signature_verification_code(int $signatureId): string
    {
        return sprintf('ASS-%06d', max(0, $signatureId));
    }
}

if (!function_exists('proto_signature_id_from_code')) {
    function proto_signature_id_from_code(string $code): int
    {
        $code = trim($code);
        if ($code === '') {
            return 0;
        }
        if (ctype_digit($code)) {
            return (int)$code;
        }
        if (preg_match('/(\d+)/', strtoupper($code), $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }
}

if (!function_exists('proto_signature_validation_url')) {
    function proto_signature_validation_url(): string
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/protocolo/index.php');
        $basePath = rtrim(dirname($scriptName), '/');
        $path = ($basePath !== '' ? $basePath : '') . '/validar_assinatura.php';

        if ($host === '') {
            return $path;
        }
        return $scheme . '://' . $host . $path;
    }
}

if (!function_exists('proto_fetch_signed_signatures')) {
    function proto_fetch_signed_signatures(mysqli $conn, int $documentoId): array
    {
        if ($documentoId <= 0) {
            return [];
        }

        $stmt = $conn->prepare('
            SELECT a.id, a.assinado_em, u.nome, v.cargo
            FROM doc_assinaturas a
            INNER JOIN usuarios u ON u.matricula = a.usuario
            LEFT JOIN (
                SELECT matricula, MAX(cargo) AS cargo
                FROM vinculo
                GROUP BY matricula
            ) v ON v.matricula = a.usuario
            WHERE a.documento_id = ? AND a.status = "assinado"
            ORDER BY a.ordem ASC, a.assinado_em ASC
        ');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $documentoId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $assinaturasInfo = [];
        foreach ($rows as $row) {
            $signatureId = (int)($row['id'] ?? 0);
            $assinaturasInfo[] = [
                'id' => $signatureId,
                'nome' => (string)($row['nome'] ?? ''),
                'cargo' => (string)($row['cargo'] ?? ''),
                'data' => !empty($row['assinado_em']) ? date('d/m/Y H:i', strtotime((string)$row['assinado_em'])) : '',
                'codigo_verificacao' => proto_signature_verification_code($signatureId),
            ];
        }

        return $assinaturasInfo;
    }
}
