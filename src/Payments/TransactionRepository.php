<?php
/**
 * src/Payments/TransactionRepository.php
 *
 * Persistência de transações de pagamento.
 */

namespace App\Payments;

use App\Database;
use PDO;
use PDOException;

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/PaymentPlans.php';
require_once __DIR__ . '/PaymentLogger.php';

class TransactionRepository
{
    private static bool $initialized = false;

    /**
     * Garante que a tabela de transações exista.
     */
    public static function ensureSchema(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        $pdo = Database::getConnection();

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_type ENUM('basic','pro','premium') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    credits INT NOT NULL,
    asaas_payment_id VARCHAR(80) NOT NULL,
    status ENUM('pending','paid','failed','expired') NOT NULL DEFAULT 'pending',
    pix_qrcode LONGTEXT NULL,
    pix_payload TEXT NULL,
    pix_expiration DATETIME NULL,
    due_date DATE NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_payment (asaas_payment_id),
    INDEX idx_user_status (user_id, status),
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            PaymentLogger::log('transactions.ensureSchema.error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Cria um registro de transação pendente.
     */
    public static function create(int $userId, string $planType, float $amount, int $credits, string $asaasPaymentId, ?string $pixQrCode, ?string $pixPayload, ?string $pixExpiration, ?string $dueDate): array
    {
        self::ensureSchema();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'INSERT INTO transactions (user_id, plan_type, amount, credits, asaas_payment_id, status, pix_qrcode, pix_payload, pix_expiration, due_date)
             VALUES (:user_id, :plan_type, :amount, :credits, :payment_id, :status, :pix_qrcode, :pix_payload, :pix_expiration, :due_date)'
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':plan_type' => $planType,
            ':amount' => $amount,
            ':credits' => $credits,
            ':payment_id' => $asaasPaymentId,
            ':status' => 'pending',
            ':pix_qrcode' => $pixQrCode,
            ':pix_payload' => $pixPayload,
            ':pix_expiration' => $pixExpiration,
            ':due_date' => $dueDate,
        ]);

        return self::findById((int) $pdo->lastInsertId());
    }

    /**
     * Recupera uma transação por ID.
     */
    public static function findById(int $transactionId): ?array
    {
        self::ensureSchema();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $transactionId]);

        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        return $transaction ?: null;
    }

    /**
     * Recupera uma transação pelo ID do pagamento Asaas.
     */
    public static function findByPaymentId(string $asaasPaymentId): ?array
    {
        self::ensureSchema();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT * FROM transactions WHERE asaas_payment_id = :payment_id LIMIT 1');
        $stmt->execute([':payment_id' => $asaasPaymentId]);

        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        return $transaction ?: null;
    }

    /**
     * Recupera uma transação pertencente ao usuário informado.
     */
    public static function findForUser(int $transactionId, int $userId): ?array
    {
        self::ensureSchema();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([
            ':id' => $transactionId,
            ':user_id' => $userId,
        ]);

        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        return $transaction ?: null;
    }

    /**
     * Atualiza status textual da transação (sem alterar créditos).
     */
    public static function updateStatus(int $transactionId, string $status): void
    {
        self::ensureSchema();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('UPDATE transactions SET status = :status WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':id' => $transactionId,
        ]);
    }

    /**
     * Marca uma transação como paga e retorna os dados atualizados.
     */
    public static function markAsPaid(string $asaasPaymentId): ?array
    {
        self::ensureSchema();
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM transactions WHERE asaas_payment_id = :payment_id FOR UPDATE');
            $stmt->execute([':payment_id' => $asaasPaymentId]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transaction) {
                $pdo->rollBack();
                return null;
            }

            if ($transaction['status'] === 'paid') {
                $pdo->commit();
                $transaction['already_paid'] = true;
                return $transaction;
            }

            $update = $pdo->prepare('UPDATE transactions SET status = :status, paid_at = NOW() WHERE id = :id');
            $update->execute([
                ':status' => 'paid',
                ':id' => $transaction['id'],
            ]);

            $pdo->commit();
            $updatedTransaction = self::findById((int) $transaction['id']) ?? $transaction;
            $updatedTransaction['already_paid'] = false;

            return $updatedTransaction;
        } catch (PDOException $e) {
            $pdo->rollBack();
            PaymentLogger::log('transactions.markPaid.error', [
                'payment_id' => $asaasPaymentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
