<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/RevisionPaper.php';

Auth::requireRole('student', '/auth/login.php');
$user = Auth::user();
$paper = PORTAL_EXTENSIONS_ENABLED ? RevisionPaper::findForStudent((int)($_GET['id'] ?? 0), (int)$user['id']) : null;
if (!$paper) { http_response_code(404); exit('Revision paper not found or not available to your account.'); }
$path = RevisionPaper::filePath((string)$paper['stored_path']);
if (!$path) { http_response_code(404); exit('Revision paper file is unavailable.'); }
$downloadName = str_replace(["\r", "\n", '"'], ['', '', '_'], basename((string)$paper['original_filename']));
header('Content-Type: ' . $paper['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
