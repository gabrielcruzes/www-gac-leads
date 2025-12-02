<?php
/**
 * src/Payments/PaymentLogger.php
 *
 * Registro simples de eventos de pagamento.
 */

namespace App\Payments;

final class PaymentLogger
{
    /**
     * Registra uma mensagem em storage/logs/payments.log.
     */
    public static function log(string $message, array $context = []): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $logDir = $projectRoot . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $logFile = $logDir . '/payments.log';
        $record = [
            'time' => date(DATE_ATOM),
            'message' => $message,
        ];

        if (!empty($context)) {
            $record['context'] = $context;
        }

        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        if ($line !== '') {
            file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
        }
    }
}
