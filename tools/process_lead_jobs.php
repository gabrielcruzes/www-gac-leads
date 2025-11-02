<?php
/**
 * tools/process_lead_jobs.php
 *
 * Script executado pelo cron para processar buscas de leads em background.
 */

use App\CasaDosDadosApi;
use App\LeadSearchJobService;
use App\SearchHistory;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/CasaDosDadosApi.php';
require_once __DIR__ . '/../src/LeadSearchJobService.php';
require_once __DIR__ . '/../src/SearchHistory.php';

$job = LeadSearchJobService::puxarJobPendenteParaProcessamento();

if (!$job) {
    echo '[' . date('Y-m-d H:i:s') . "] Nenhum job pendente.\n";
    return;
}

$jobId = (int) $job['id'];
echo '[' . date('Y-m-d H:i:s') . "] Processando job #{$jobId}...\n";

try {
    $dadosFiltros = is_array($job['filters']) ? $job['filters'] : [];
    $formFilters = isset($dadosFiltros['form']) && is_array($dadosFiltros['form']) ? $dadosFiltros['form'] : [];
    $apiFilters = isset($dadosFiltros['api']) && is_array($dadosFiltros['api']) ? $dadosFiltros['api'] : $formFilters;

    if (!is_array($apiFilters)) {
        $apiFilters = [];
    }

    $apiFilters['quantidade'] = $job['quantity'];
    if (!isset($apiFilters['pagina'])) {
        $apiFilters['pagina'] = $formFilters['pagina'] ?? 1;
    }

    LeadSearchJobService::atualizarProgresso($jobId, 10);

    $api = new CasaDosDadosApi();
    $leads = $api->buscarLeads($apiFilters);

    LeadSearchJobService::atualizarProgresso($jobId, 80);
    LeadSearchJobService::concluirJob($jobId, $leads);

    try {
        $historicoFiltros = !empty($formFilters) ? $formFilters : $apiFilters;
        SearchHistory::registrar($job['user_id'], $historicoFiltros, count($leads));
    } catch (\Throwable $historyException) {
        error_log('Falha ao registrar historico do job ' . $jobId . ': ' . $historyException->getMessage());
    }

    echo '[' . date('Y-m-d H:i:s') . "] Job #{$jobId} concluido com " . count($leads) . " leads.\n";
} catch (\Throwable $exception) {
    LeadSearchJobService::falharJob($jobId, $exception->getMessage());
    echo '[' . date('Y-m-d H:i:s') . "] Job #{$jobId} falhou: " . $exception->getMessage() . "\n";
    exit(1);
}
