<?php
/**
 * src/Payments/AsaasException.php
 *
 * Erros originados da API do Asaas.
 */

namespace App\Payments;

require_once __DIR__ . '/PaymentException.php';

class AsaasException extends PaymentException
{
    public function __construct(string $message, int $statusCode = 502, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }
}
