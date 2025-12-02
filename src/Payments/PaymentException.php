<?php
/**
 * src/Payments/PaymentException.php
 *
 * Exceção específica para fluxos de pagamento com suporte a códigos HTTP.
 */

namespace App\Payments;

use RuntimeException;

class PaymentException extends RuntimeException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
