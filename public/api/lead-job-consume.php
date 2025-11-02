<?php
/**
 * public/api/lead-job-consume.php
 *
 * Consome os resultados de um job concluido e os disponibiliza na sessao do usuario.
 */

use App\Auth;
use App\LeadSearchJobService;
use App\LeadService;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/LeadSearchJobService.php';
require_once __DIR__ . '/../../src/LeadService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$jobId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
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
if ($delivered) {
    echo json_encode(['success' => true, 'message' => 'Resultados ja foram disponibilizados.']);
    exit;
}

if ($job['status'] !== 'completed') {
    http_response_code(409);
    echo json_encode(['error' => 'O job ainda nao foi finalizado.']);
    exit;
}

$rawLeads = is_array($job['results']) ? $job['results'] : [];
$filtersData = isset($job['filters']) && is_array($job['filters']) ? $job['filters'] : [];
$formFilters = isset($filtersData['form']) && is_array($filtersData['form']) ? $filtersData['form'] : [];
$apiFilters = isset($filtersData['api']) && is_array($filtersData['api']) ? $filtersData['api'] : $formFilters;
if (empty($apiFilters)) {
    $apiFilters = $formFilters;
}
$preparedLeads = LeadService::armazenarLeadsNaSessao($rawLeads, $apiFilters);
$exportFilters = !empty($formFilters) ? $formFilters : $apiFilters;
$segmentoExport = $apiFilters['segmento_label'] ?? ($exportFilters['cnae'] ?? 'Consulta personalizada');
$_SESSION['job_latest_results'] = $preparedLeads;
$_SESSION['last_search_export'] = [
    'segmento' => $segmentoExport,
    'leads' => $preparedLeads,
    'filters' => $exportFilters,
];
$_SESSION['last_search_token'] = bin2hex(random_bytes(8));
$_SESSION['flash_success'] = 'Busca concluida! Os resultados foram carregados.';

LeadSearchJobService::marcarEntregue($jobId);

echo json_encode([
    'success' => true,
    'leads' => count($preparedLeads),
]);
