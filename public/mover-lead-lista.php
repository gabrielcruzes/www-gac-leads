<?php
/**
 * public/mover-lead-lista.php
 *
 * Move um lead salvo de uma lista para outra lista do usuario.
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
    header('Location: listas.php');
    exit;
}

$itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
$listaDestinoId = isset($_POST['lista_destino']) ? (int) $_POST['lista_destino'] : 0;
$redirect = trim($_POST['redirect'] ?? 'listas.php');

if ($itemId <= 0 || $listaDestinoId <= 0) {
    $_SESSION['flash_error'] = 'Selecione um lead e a lista de destino.';
    header('Location: ' . $redirect);
    exit;
}

if (LeadListService::moverItem($userId, $itemId, $listaDestinoId)) {
    $_SESSION['flash_success'] = 'Lead movido para a nova lista com sucesso.';
} else {
    $_SESSION['flash_error'] = 'Nao foi possivel mover o lead para a lista selecionada.';
}

header('Location: ' . $redirect);
exit;

