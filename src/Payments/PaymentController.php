<?php
/**
 * src/Payments/PaymentController.php
 *
 * Orquestra requisições de pagamento e webhook.
 */

namespace App\Payments;

use App\CreditService;
use Throwable;

require_once __DIR__ . '/../CreditService.php';
require_once __DIR__ . '/AsaasService.php';
require_once __DIR__ . '/PaymentPlans.php';
require_once __DIR__ . '/TransactionRepository.php';
require_once __DIR__ . '/PaymentException.php';
require_once __DIR__ . '/PaymentLogger.php';

class PaymentController
{
    /**
     * Inicia uma cobrança PIX e retorna o QR Code.
     *
     * @param array{planType?:string,customer?:array{name?:string,email?:string,cpfCnpj?:string,mobilePhone?:string}} $payload
     */
    public static function createPayment(int $userId, array $payload): array
    {
        $planType = strtolower($payload['planType'] ?? '');
        $plan = PaymentPlans::getPlan($planType);

        if (!$plan) {
            throw new PaymentException('Plano inválido.', 422);
        }

        $customer = $payload['customer'] ?? [];
        $name = trim($customer['name'] ?? '');
        $email = trim($customer['email'] ?? '');
        $cpfCnpj = preg_replace('/\D+/', '', $customer['cpfCnpj'] ?? '');
        $mobilePhone = preg_replace('/\D+/', '', $customer['mobilePhone'] ?? '');

        if ($name === '' || $email === '' || $cpfCnpj === '') {
            throw new PaymentException('Nome, email e CPF/CNPJ são obrigatórios.', 422);
        }

        $asaas = new AsaasService();
        $customerPayload = [
            'name' => $name,
            'email' => $email,
            'cpfCnpj' => $cpfCnpj,
        ];

        if ($mobilePhone) {
            $customerPayload['mobilePhone'] = $mobilePhone;
        }

        $customerResponse = $asaas->createCustomer($customerPayload);
        $paymentResponse = $asaas->createPixPayment(
            $customerResponse['id'],
            $plan['description'],
            (float) $plan['amount'],
            date('Y-m-d', strtotime('+1 day'))
        );

        $pixData = $paymentResponse['pixTransaction'] ?? [];
        $pixEncoded = $pixData['encodedImage'] ?? null;
        $pixPayload = $pixData['payload'] ?? null;
        $pixExpiration = $pixData['expirationDate'] ?? null;

        if (!$pixEncoded || !$pixPayload) {
            throw new PaymentException('Dados PIX não retornados pelo Asaas.', 502);
        }

        $transaction = TransactionRepository::create(
            $userId,
            $planType,
            (float) $plan['amount'],
            (int) $plan['credits'],
            $paymentResponse['id'],
            $pixEncoded,
            $pixPayload,
            $pixExpiration,
            $paymentResponse['dueDate'] ?? null
        );

        PaymentLogger::log('payment.created', [
            'user_id' => $userId,
            'plan' => $planType,
            'transaction_id' => $transaction['id'],
            'asaas_payment_id' => $paymentResponse['id'],
        ]);

        return self::formatResponse($transaction);
    }

    /**
     * Trata o webhook recebido do Asaas.
     */
    public static function handleWebhook(array $payload, string $providedToken): array
    {
        $asaas = new AsaasService();

        if (!$asaas->validateWebhook($providedToken)) {
            throw new PaymentException('Webhook não autorizado.', 401);
        }

        $event = $payload['event'] ?? '';
        $payment = $payload['payment'] ?? [];
        $paymentId = $payment['id'] ?? '';
        $status = strtoupper($payment['status'] ?? '');

        if ($paymentId === '') {
            throw new PaymentException('Webhook inválido: payment.id ausente.', 422);
        }

        PaymentLogger::log('webhook.received', [
            'event' => $event,
            'payment_id' => $paymentId,
            'status' => $status,
        ]);

        if (!in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
            return ['ignored' => true];
        }

        if (!in_array($status, ['RECEIVED', 'RECEIVED_IN_CASH', 'CONFIRMED'], true)) {
            return ['ignored' => true];
        }

        $transaction = TransactionRepository::markAsPaid($paymentId);

        if (!$transaction) {
            throw new PaymentException('Transação não encontrada.', 404);
        }

        $alreadyPaid = (bool) ($transaction['already_paid'] ?? false);
        if (!$alreadyPaid && (int) ($transaction['credits'] ?? 0) > 0) {
            CreditService::creditarUsuario((int) $transaction['user_id'], (int) $transaction['credits']);
        }

        PaymentLogger::log('payment.paid', [
            'transaction_id' => $transaction['id'],
            'user_id' => $transaction['user_id'],
            'plan' => $transaction['plan_type'],
        ]);

        return ['processed' => true];
    }

    /**
     * Consulta o status de uma transação do usuário autenticado.
     */
    public static function getStatus(int $transactionId, int $userId): array
    {
        $transaction = TransactionRepository::findForUser($transactionId, $userId);

        if (!$transaction) {
            throw new PaymentException('Transação não encontrada.', 404);
        }

        return self::formatResponse($transaction);
    }

    private static function formatResponse(array $transaction): array
    {
        $plan = PaymentPlans::getPlan($transaction['plan_type']);

        $pixQrCode = $transaction['pix_qrcode'] ?? '';
        $pixPayload = $transaction['pix_payload'] ?? '';

        return [
            'id' => $transaction['id'],
            'status' => strtoupper($transaction['status']),
            'value' => (float) $transaction['amount'],
            'credits' => (int) $transaction['credits'],
            'asaasPaymentId' => $transaction['asaas_payment_id'],
            'plan' => [
                'type' => $transaction['plan_type'],
                'name' => $plan['name'] ?? '',
            ],
            'pix' => [
                'qrcode' => $pixQrCode ? 'data:image/png;base64,' . $pixQrCode : null,
                'payload' => $pixPayload,
                'expirationDate' => $transaction['pix_expiration'],
            ],
            'createdAt' => $transaction['created_at'],
            'paidAt' => $transaction['paid_at'],
        ];
    }
}
