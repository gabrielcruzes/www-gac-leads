<?php
/**
 * public/api/payments/status.php
 *
 * Consulta o status de uma transação de pagamento.
 */

use App\Auth;
use App\Payments\PaymentController;
use App\Payments\PaymentException;
use Throwable;

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../src/Auth.php';
require_once __DIR__ . '/../../../src/Payments/PaymentController.php';
require_once __DIR__ . '/../../../src/Payments/PaymentException.php';

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

$transactionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($transactionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Transacao invalida.']);
    exit;
}

try {
    $payment = PaymentController::getStatus($transactionId, (int) $user['id']);
    echo json_encode([
        'success' => true,
        'payment' => $payment,
    ]);
} catch (PaymentException $e) {
    http_response_code($e->getStatusCode());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
} catch (Throwable $e) {
    error_log('[payments.status] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno ao consultar pagamento.',
    ]);
}
