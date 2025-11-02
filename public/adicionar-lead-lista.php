<?php
/**
 * public/adicionar-lead-lista.php
 *
 * Endpoint para adicionar um ou mais leads a uma lista do usuario.
 */

use App\Auth;
use App\LeadListService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/LeadListService.php';

Auth::requireLogin();

$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: buscar-leads.php');
    exit;
}

$listaId = isset($_POST['lista_id']) ? (int) $_POST['lista_id'] : 0;
$redirect = $_POST['redirect'] ?? 'listas.php';

$leadTokens = [];
$leadTokenUnico = trim($_POST['lead_token'] ?? '');
if ($leadTokenUnico !== '') {
    $leadTokens[] = $leadTokenUnico;
}

if (isset($_POST['lead_tokens']) && is_array($_POST['lead_tokens'])) {
    foreach ($_POST['lead_tokens'] as $token) {
        $token = trim((string) $token);
        if ($token !== '') {
            $leadTokens[] = $token;
        }
    }
}

$leadTokens = array_values(array_unique($leadTokens));

if ($listaId <= 0) {
    $_SESSION['flash_error'] = 'Selecione uma lista valida.';
    header('Location: ' . $redirect);
    exit;
}

if (empty($leadTokens)) {
    $_SESSION['flash_error'] = 'Selecione ao menos um lead.';
    header('Location: ' . $redirect);
    exit;
}

$sucesso = 0;
$ultimoCredito = null;
$erros = [];

foreach ($leadTokens as $token) {
    $resultado = LeadListService::adicionarLead($userId, $listaId, $token);

    if (!empty($resultado['success'])) {
        $sucesso++;
        if (isset($resultado['credits'])) {
            $ultimoCredito = (int) $resultado['credits'];
        }
    } else {
        $erros[] = $resultado['message'] ?? 'Nao foi possivel adicionar o lead.';
    }
}

if ($sucesso > 0) {
    $mensagem = $sucesso === 1
        ? 'Lead adicionado a lista com sucesso.'
        : $sucesso . ' leads adicionados a lista com sucesso.';

    if ($ultimoCredito !== null) {
        $mensagem .= ' Creditos restantes: ' . $ultimoCredito . '.';
    }

    $_SESSION['flash_success'] = $mensagem;
}

if (!empty($erros)) {
    $_SESSION['flash_error'] = implode(' ', array_unique($erros));
}

header('Location: ' . $redirect);
exit;
