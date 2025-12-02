<?php
/**
 * src/Payments/PaymentPlans.php
 *
 * Catálogo centralizado dos planos comercializados.
 */

namespace App\Payments;

final class PaymentPlans
{
    private const PLANS = [
        'basic' => [
            'name' => 'Plano Básico',
            'amount' => 50.00,
            'credits' => 150,
            'description' => 'Plano Básico - 150 Créditos',
        ],
        'pro' => [
            'name' => 'Plano Pro',
            'amount' => 200.00,
            'credits' => 1000,
            'description' => 'Plano Pro - 1.000 Créditos',
        ],
        'premium' => [
            'name' => 'Plano Premium',
            'amount' => 300.00,
            'credits' => 2000,
            'description' => 'Plano Premium - 2.000 Créditos',
        ],
    ];

    /**
     * Retorna os detalhes completos de um plano.
     */
    public static function getPlan(string $type): ?array
    {
        $type = strtolower(trim($type));
        return self::PLANS[$type] ?? null;
    }

    /**
     * Retorna todos os planos disponíveis.
     */
    public static function all(): array
    {
        return self::PLANS;
    }
}
