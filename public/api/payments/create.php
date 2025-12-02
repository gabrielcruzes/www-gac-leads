<?php
/**
 * public/api/payments/create.php
 *
 * Inicia uma compra de créditos via PIX (Asaas).
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

$rawInput = file_get_contents('php://input');
$data = [];
if ($rawInput !== false && $rawInput !== '') {
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON invalido.']);
        exit;
    }
} else {
    $data = $_POST;
}

try {
    $payment = PaymentController::createPayment((int) $user['id'], $data);
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
    error_log('[payments.create] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno ao criar pagamento.',
    ]);
}
