<?php
/**
 * public/api/payments/webhook.php
 *
 * Endpoint para notificações da Asaas.
 */

use App\Payments\PaymentController;
use App\Payments\PaymentException;
use Throwable;

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../src/Payments/PaymentController.php';
require_once __DIR__ . '/../../../src/Payments/PaymentException.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo nao permitido.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = [];
if ($rawInput !== false && $rawInput !== '') {
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON invalido.']);
        exit;
    }
}

$token = $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ($_GET['token'] ?? '');

try {
    $result = PaymentController::handleWebhook($data, $token);
    echo json_encode([
        'success' => true,
        'result' => $result,
    ]);
} catch (PaymentException $e) {
    http_response_code($e->getStatusCode());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
} catch (Throwable $e) {
    error_log('[payments.webhook] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno ao processar webhook.',
    ]);
}
