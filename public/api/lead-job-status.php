<?php
/**
 * public/api/lead-job-status.php
 *
 * Retorna o status atual de um job de busca de leads.
 */

use App\Auth;
use App\LeadSearchJobService;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/LeadSearchJobService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo nao permitido.']);
    exit;
}

$user = Auth::user();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nao autenticado.']);
    exit;
}

$jobId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($jobId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador de job invalido.']);
    exit;
}

$job = LeadSearchJobService::buscarJobDoUsuario($jobId, (int) $user['id']);
if (!$job) {
    http_response_code(404);
    echo json_encode(['error' => 'Job nao encontrado.']);
    exit;
}

$delivered = isset($job['delivered_at']) && $job['delivered_at'] instanceof \DateTime;

echo json_encode([
    'id' => $job['id'],
    'status' => $job['status'],
    'progress' => $job['progress'],
    'quantity' => $job['quantity'],
    'can_consume' => $job['status'] === 'completed' && !$delivered,
    'error' => $job['error_message'] ?? '',
    'updated_at' => $job['updated_at'] instanceof \DateTime ? $job['updated_at']->format(DATE_ATOM) : null,
]);
