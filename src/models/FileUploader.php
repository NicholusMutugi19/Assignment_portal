<?php
/**
 * File Upload Handler – CGI-principled secure upload
 * assignment_portal/src/models/FileUploader.php
 */

require_once __DIR__ . '/../config/database.php';

class FileUploader
{
    private array  $errors  = [];
    private string $destDir;

    public function __construct(string $destDir)
    {
        $this->destDir = rtrim($destDir, '/') . '/';
    }

    /**
     * Validate and move an uploaded file.
     * Returns ['ok'=>true,'path'=>...,'name'=>...,'size'=>...,'mime'=>...] or ['ok'=>false,'errors'=>[...]]
     */
    public function handle(array $file): array
    {
        $this->errors = [];

        // 1. Basic PHP upload error check
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->phpUploadErrorMessage($file['error']);
            return $this->fail();
        }

        // 2. Verify it is a genuine upload (CGI security principle)
        if (!is_uploaded_file($file['tmp_name'])) {
            $this->errors[] = 'Invalid upload source — possible injection attempt.';
            return $this->fail();
        }

        // 3. Size check
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $this->errors[] = sprintf(
                'File size %s exceeds the maximum allowed %s.',
                $this->humanSize($file['size']),
                $this->humanSize(UPLOAD_MAX_SIZE)
            );
            return $this->fail();
        }

        // 4. Extension whitelist
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
            $this->errors[] = sprintf(
                'File type ".%s" is not allowed. Accepted: %s.',
                htmlspecialchars($ext),
                implode(', ', array_map(fn($e) => ".$e", UPLOAD_ALLOWED_EXT))
            );
            return $this->fail();
        }

        // 5. MIME type validation — read magic bytes, do NOT trust browser header
        $detectedMime = $this->detectMime($file['tmp_name'], $ext);
        if (!in_array($detectedMime, UPLOAD_ALLOWED_TYPES, true)) {
            $this->errors[] = 'File content does not match its extension (MIME mismatch).';
            return $this->fail();
        }

        // 6. Sanitise filename and generate unique storage name
        $safeName    = $this->sanitiseFilename($file['name']);
        $storedName  = uniqid('', true) . '_' . $safeName;
        $destination = $this->destDir . $storedName;

        // 7. Ensure destination directory exists
        if (!is_dir($this->destDir) && !mkdir($this->destDir, 0755, true)) {
            $this->errors[] = 'Upload directory could not be created.';
            return $this->fail();
        }

        // 8. Move file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->errors[] = 'Could not save the uploaded file. Please try again.';
            return $this->fail();
        }

        return [
            'ok'           => true,
            'path'         => $destination,
            'stored_name'  => $storedName,
            'original_name'=> $file['name'],
            'size'         => $file['size'],
            'mime'         => $detectedMime,
        ];
    }

    // ------------------------------------------------------------------ //

    private function detectMime(string $tmpPath, string $ext): string
    {
        // Use finfo for real magic-byte detection
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            // ZIP files can appear as application/octet-stream or x-zip-compressed
            if ($ext === 'zip' && in_array($mime, ['application/octet-stream','application/x-zip-compressed','application/zip'], true)) {
                return 'application/zip';
            }
            return $mime;
        }
        // Fallback: extension-to-MIME map
        $map = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'zip'  => 'application/zip',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }

    private function sanitiseFilename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        return substr($name, 0, 200);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 2)    . ' KB';
        return $bytes . ' B';
    }

    private function phpUploadErrorMessage(int $code): string
    {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by a PHP extension.',
        ];
        return $msgs[$code] ?? "Unknown upload error (code $code).";
    }

    private function fail(): array
    {
        return ['ok' => false, 'errors' => $this->errors];
    }
}
