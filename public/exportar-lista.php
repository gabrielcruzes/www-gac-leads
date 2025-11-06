<?php
/**
 * public/exportar-lista.php
 *
 * Gera arquivos CSV a partir de uma lista salva.
 */

use App\Auth;
use App\ExportService;
use App\LeadListService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/ExportService.php';
require_once __DIR__ . '/../src/LeadListService.php';

Auth::requireLogin();

$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listas.php');
    exit;
}

$listaId = isset($_POST['lista_id']) ? (int) $_POST['lista_id'] : 0;

if ($listaId <= 0) {
    $_SESSION['flash_error'] = 'Lista invalida.';
    header('Location: listas.php');
    exit;
}

$lista = LeadListService::obterLista($userId, $listaId);
if (!$lista) {
    $_SESSION['flash_error'] = 'Lista nao encontrada.';
    header('Location: listas.php');
    exit;
}

$leads = LeadListService::listarItens($userId, $listaId);
if (empty($leads)) {
    $_SESSION['flash_error'] = 'A lista nao possui leads para exportar.';
    header('Location: lista-detalhe.php?id=' . $listaId);
    exit;
}

$dadosExportacao = array_map(
    static function (array $lead): array {
        if (array_key_exists('item_id', $lead)) {
            unset($lead['item_id']);
        }

        return $lead;
    },
    $leads
);

$arquivo = ExportService::gerarCsv($userId, $lista['name'], $dadosExportacao);

if ($arquivo) {
    try {
        $token = bin2hex(random_bytes(16));
    } catch (\Throwable $exception) {
        $token = sha1(uniqid((string) $userId, true));
    }

    $_SESSION['last_export_ready'] = [
        'token' => $token,
        'path' => $arquivo,
        'filename' => basename($arquivo),
    ];
    $_SESSION['flash_success'] = 'Exportacao gerada com sucesso!';
    $_SESSION['flash_export_download'] = 'download-export.php?token=' . urlencode($token);
} else {
    $_SESSION['flash_error'] = 'Nao foi possivel gerar o arquivo CSV.';
}

header('Location: lista-detalhe.php?id=' . $listaId);
exit;
