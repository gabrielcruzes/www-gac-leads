<?php
/**
 * src/Payments/AsaasService.php
 *
 * Camada de integração com a API oficial do Asaas.
 */

namespace App\Payments;

use Throwable;

require_once __DIR__ . '/AsaasException.php';
require_once __DIR__ . '/PaymentLogger.php';
require_once __DIR__ . '/PaymentException.php';

class AsaasService
{
    private string $apiKey;
    private string $environment;
    private string $baseUrl;
    private ?string $webhookToken;

    public function __construct(?string $apiKey = null, ?string $environment = null, ?string $webhookToken = null)
    {
        $this->apiKey = $apiKey ?? env('ASAAS_API_KEY');
        $this->environment = strtolower(trim($environment ?? env('ASAAS_ENVIRONMENT', 'sandbox')));
        $this->webhookToken = $webhookToken ?? env('ASAAS_WEBHOOK_TOKEN');
        $this->baseUrl = $this->environment === 'production'
            ? 'https://api.asaas.com/v3'
            : 'https://sandbox.asaas.com/api/v3';

        if (empty($this->apiKey)) {
            throw new PaymentException('Chave da API Asaas não configurada.', 500);
        }
    }

    /**
     * Cria ou busca cliente pelo CPF/CNPJ ou email.
     */
    public function createCustomer(array $customerData): array
    {
        $cpfCnpj = $customerData['cpfCnpj'] ?? '';
        $email = $customerData['email'] ?? '';

        if ($cpfCnpj) {
            $found = $this->getCustomerByDocument($cpfCnpj);
            if ($found) {
                return $found;
            }
        }

        if ($email) {
            $found = $this->getCustomerByEmail($email);
            if ($found) {
                return $found;
            }
        }

        return $this->request('POST', '/customers', $customerData);
    }

    /**
     * Cria uma cobrança PIX com base no plano escolhido.
     */
    public function createPixPayment(string $customerId, string $description, float $amount, ?string $dueDate = null): array
    {
        $dueDate = $dueDate ?: date('Y-m-d', strtotime('+1 day'));

        $payload = [
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => $amount,
            'dueDate' => $dueDate,
            'description' => $description,
        ];

        $payment = $this->request('POST', '/payments', $payload);

        if (!isset($payment['pixTransaction'])) {
            throw new AsaasException('Resposta inválida: pixTransaction ausente.', 502);
        }

        return $payment;
    }

    /**
     * Consulta o status de uma cobrança.
     */
    public function getPaymentStatus(string $paymentId): array
    {
        return $this->request('GET', '/payments/' . urlencode($paymentId));
    }

    /**
     * Valida o token recebido no webhook.
     */
    public function validateWebhook(?string $token): bool
    {
        if (empty($this->webhookToken)) {
            throw new PaymentException('Token de webhook não configurado.', 500);
        }

        if ($token === null || $token === '') {
            return false;
        }

        return hash_equals($this->webhookToken, $token);
    }

    /**
     * Busca cliente pelo documento.
     */
    private function getCustomerByDocument(string $cpfCnpj): ?array
    {
        $params = http_build_query(['cpfCnpj' => $cpfCnpj]);
        $result = $this->request('GET', '/customers?' . $params);

        if (!empty($result['data'][0])) {
            return $result['data'][0];
        }

        return null;
    }

    /**
     * Busca cliente pelo email.
     */
    private function getCustomerByEmail(string $email): ?array
    {
        $params = http_build_query(['email' => $email]);
        $result = $this->request('GET', '/customers?' . $params);

        if (!empty($result['data'][0])) {
            return $result['data'][0];
        }

        return null;
    }

    /**
     * Executa requisições HTTP autenticadas para o Asaas.
     */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        $method = strtoupper($method);

        $url = rtrim($this->baseUrl, '/') . $endpoint;
        $curl = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'access_token: ' . $this->apiKey,
        ];

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];

        if (!empty($payload)) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $options);
        $responseBody = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($responseBody === false || $curlError) {
            PaymentLogger::log('asaas.request.failure', [
                'endpoint' => $endpoint,
                'error' => $curlError ?: 'Unknown error',
            ]);

            throw new AsaasException('Falha na comunicação com o Asaas.', 502);
        }

        $decoded = json_decode($responseBody, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            PaymentLogger::log('asaas.request.invalid_json', [
                'endpoint' => $endpoint,
                'body' => substr($responseBody, 0, 500),
            ]);

            throw new AsaasException('Resposta inválida do Asaas.', 502);
        }

        if ($httpCode >= 400) {
            $message = $decoded['errors'][0]['description'] ?? ($decoded['message'] ?? 'Erro ao chamar API');
            PaymentLogger::log('asaas.request.http_error', [
                'endpoint' => $endpoint,
                'status' => $httpCode,
                'message' => $message,
            ]);

            $status = $httpCode >= 500 ? 502 : $httpCode;
            throw new AsaasException($message, $status);
        }

        return $decoded;
    }
}
