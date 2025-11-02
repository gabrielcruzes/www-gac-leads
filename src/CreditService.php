<?php
/**
 * src/CreditService.php
 *
 * Responsável por orquestrar compras e crédito de usuários.
 */

namespace App;

use Throwable;

require_once __DIR__ . '/Database.php';

class CreditService
{
    /**
     * Cria uma ordem de crédito simples.
     */
    public static function criarOrdemCredito(int $userId, int $credits, float $amount, string $paymentStatus = 'paid'): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO credit_orders (user_id, credits, amount, payment_status) VALUES (:user_id, :credits, :amount, :status)');
        $stmt->execute([
            ':user_id' => $userId,
            ':credits' => $credits,
            ':amount' => $amount,
            ':status' => $paymentStatus,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Atualiza os créditos do usuário somando os novos créditos.
     */
    public static function creditarUsuario(int $userId, int $credits): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE users SET credits = credits + :credits WHERE id = :id');

        return $stmt->execute([
            ':credits' => $credits,
            ':id' => $userId,
        ]);
    }

    /**
     * Fluxo simplificado de compra com pagamento simulado.
     */
    public static function comprarCreditosSimples(int $userId, int $credits, float $amount): bool
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            self::criarOrdemCredito($userId, $credits, $amount, 'paid'); // TODO integrar gateway de pagamento real
            self::creditarUsuario($userId, $credits);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log(sprintf(
                '[CreditService] Falha ao comprar créditos (user_id=%d, credits=%d, amount=%.2f): %s | trace: %s',
                $userId,
                $credits,
                $amount,
                $e->getMessage(),
                $e->getTraceAsString()
            ));
            return false;
        }
    }
}
