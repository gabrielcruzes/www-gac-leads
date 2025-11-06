<?php
/**
 * public/download-export.php
 *
 * Entrega arquivos de exportacao gerados pelo usuario de forma segura.
 */

use App\Auth;
use App\ExportService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/ExportService.php';

Auth::requireLogin();

$user = Auth::user();
if ($user === null) {
    http_response_code(404);
    echo 'Arquivo indisponivel.';
    exit;
}

$token = $_GET['token'] ?? '';
$exportIdParam = $_GET['export_id'] ?? '';

$relativePath = '';
$filename = '';

if ($token !== '') {
    $exportInfo = $_SESSION['last_export_ready'] ?? null;

    if (!$exportInfo || !isset($exportInfo['token']) || !hash_equals((string) $exportInfo['token'], (string) $token)) {
        http_response_code(404);
        echo 'Arquivo indisponivel.';
        exit;
    }

    $relativePath = (string) ($exportInfo['path'] ?? '');
    $filenameInfo = (string) ($exportInfo['filename'] ?? '');
    $filename = basename($filenameInfo !== '' ? $filenameInfo : $relativePath);
} elseif ($exportIdParam !== '') {
    $exportId = (int) $exportIdParam;
    if ($exportId <= 0) {
        http_response_code(404);
        echo 'Arquivo indisponivel.';
        exit;
    }

    $exportRecord = ExportService::obterExportacao((int) ($user['id'] ?? 0), $exportId);
    if ($exportRecord === null) {
        http_response_code(404);
        echo 'Arquivo indisponivel.';
        exit;
    }

    $relativePath = (string) ($exportRecord['file_path'] ?? '');
    $filename = basename((string) ($relativePath !== '' ? $relativePath : ('export_' . $exportId . '.csv')));
} else {
    http_response_code(404);
    echo 'Arquivo indisponivel.';
    exit;
}

$storageBase = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'exports');
if ($storageBase === false) {
    http_response_code(404);
    echo 'Arquivo indisponivel.';
    exit;
}

$absolutePath = false;
if ($filename !== '') {
    $candidate = $storageBase . DIRECTORY_SEPARATOR . $filename;
    if (is_file($candidate)) {
        $absolutePath = $candidate;
    } else {
        $realCandidate = realpath($candidate);
        if ($realCandidate !== false && is_file($realCandidate)) {
            $absolutePath = $realCandidate;
        }
    }
}

if ($absolutePath === false && $relativePath !== '') {
    $normalizedRelative = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
    $candidate = dirname(__DIR__) . DIRECTORY_SEPARATOR . $normalizedRelative;
    if (is_file($candidate)) {
        $absolutePath = $candidate;
    } else {
        $realCandidate = realpath($candidate);
        if ($realCandidate !== false && is_file($realCandidate)) {
            $absolutePath = $realCandidate;
        }
    }
}

if ($absolutePath === false) {
    http_response_code(404);
    echo 'Arquivo indisponivel.';
    exit;
}

$comparisonAbsolute = $absolutePath;
$comparisonBase = $storageBase;
if (DIRECTORY_SEPARATOR === '\\') {
    $comparisonAbsolute = strtolower($comparisonAbsolute);
    $comparisonBase = strtolower($comparisonBase);
}

if (strpos($comparisonAbsolute, $comparisonBase) !== 0 || !is_file($absolutePath) || !is_readable($absolutePath)) {
    http_response_code(404);
    echo 'Arquivo indisponivel.';
    exit;
}

$filename = $filename !== '' ? $filename : basename($absolutePath);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($absolutePath));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($absolutePath);
exit;
