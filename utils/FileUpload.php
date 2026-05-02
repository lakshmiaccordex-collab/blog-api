<?php
// utils/FileUpload.php

class FileUpload {
    private string $uploadDir;
    private int    $maxSize;
    private array  $allowedTypes;

    public function __construct() {
        $this->uploadDir    = __DIR__ . '/../' . (Database::getEnv('UPLOAD_DIR', 'uploads/'));
        $this->maxSize      = (int) Database::getEnv('MAX_FILE_SIZE', '2097152'); // 2MB default
        $this->allowedTypes = explode(',', Database::getEnv('ALLOWED_TYPES', 'image/jpeg,image/png,image/webp'));
    }

    public function upload(array $file, string $subfolder = ''): string {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload error: ' . $file['error']);
        }

        if ($file['size'] > $this->maxSize) {
            throw new RuntimeException('File size exceeds limit of ' . ($this->maxSize / 1024 / 1024) . 'MB.');
        }

        $mimeType = mime_content_type($file['tmp_name']);
        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new RuntimeException('File type not allowed. Allowed: ' . implode(', ', $this->allowedTypes));
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . strtolower($ext);
        $dir      = $this->uploadDir . ($subfolder ? rtrim($subfolder, '/') . '/' : '');

        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $destination = $dir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }

        return ($subfolder ? rtrim($subfolder, '/') . '/' : '') . $filename;
    }

    public function delete(string $relativePath): void {
        $fullPath = $this->uploadDir . $relativePath;
        if (file_exists($fullPath)) unlink($fullPath);
    }
}
