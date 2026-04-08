<?php

if (!function_exists('proto_allowed_attachment_extensions')) {
    function proto_allowed_attachment_extensions(): array
    {
        return [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'gif' => ['image/gif'],
        ];
    }
}

if (!function_exists('proto_normalize_uploaded_files')) {
    function proto_normalize_uploaded_files(array $filesField): array
    {
        if (!isset($filesField['name'])) {
            return [];
        }

        if (!is_array($filesField['name'])) {
            return [$filesField];
        }

        $files = [];
        foreach ($filesField['name'] as $index => $name) {
            $files[] = [
                'name' => $name,
                'type' => $filesField['type'][$index] ?? '',
                'tmp_name' => $filesField['tmp_name'][$index] ?? '',
                'error' => $filesField['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $filesField['size'][$index] ?? 0,
            ];
        }
        return $files;
    }
}

if (!function_exists('proto_store_attachments')) {
    function proto_store_attachments(mysqli $conn, int $documentoId, int $matricula, array $filesField, string $inputLabel = 'anexos'): void
    {
        if ($documentoId <= 0 || $matricula <= 0) {
            throw new RuntimeException('Dados inválidos para envio de anexos.');
        }

        $files = proto_normalize_uploaded_files($filesField);
        if (empty($files)) {
            return;
        }

        $allowed = proto_allowed_attachment_extensions();
        $folder = __DIR__ . '/../uploads/docs/' . $documentoId;
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new RuntimeException('Não foi possível criar a pasta de anexos do documento.');
        }

        $stmt = $conn->prepare('INSERT INTO doc_anexos (documento_id, nome_arquivo, mime_type, tamanho_bytes, caminho_storage, enviado_por) VALUES (?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            throw new RuntimeException('Não foi possível preparar o cadastro dos anexos.');
        }

        foreach ($files as $file) {
            $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                $name = (string)($file['name'] ?? $inputLabel);
                throw new RuntimeException("Falha no upload do arquivo {$name}.");
            }

            $originalName = basename((string)($file['name'] ?? ''));
            if ($originalName === '') {
                throw new RuntimeException('Nome de arquivo inválido.');
            }

            $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension === '' || !isset($allowed[$extension])) {
                throw new RuntimeException("Formato não permitido em {$originalName}. Envie PDF, Word, Excel ou imagem.");
            }

            $detectedMime = mime_content_type((string)($file['tmp_name'] ?? '')) ?: ((string)($file['type'] ?? 'application/octet-stream'));
            if (!in_array($detectedMime, $allowed[$extension], true)) {
                if (!($detectedMime === 'application/octet-stream' && in_array('application/octet-stream', $allowed[$extension], true))) {
                    throw new RuntimeException("Tipo de arquivo inválido em {$originalName}.");
                }
            }

            $filename = uniqid('doc_', true) . '.' . $extension;
            $target = $folder . '/' . $filename;
            if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
                throw new RuntimeException("Não foi possível salvar o arquivo {$originalName}.");
            }

            $path = 'uploads/docs/' . $documentoId . '/' . $filename;
            $size = (int)($file['size'] ?? 0);
            $stmt->bind_param('issisi', $documentoId, $originalName, $detectedMime, $size, $path, $matricula);
            $stmt->execute();
        }

        $stmt->close();
    }
}
