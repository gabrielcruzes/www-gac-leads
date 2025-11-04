<?php
/**
 * src/SearchHistory.php
 *
 * Persistencia e consulta do historico de buscas de CNAE.
 */

namespace App;

use PDO;

require_once __DIR__ . '/Database.php';

class SearchHistory
{
    /**
     * Registra uma nova busca para o usuario.
     */
    public static function registrar(int $userId, array $filtros, int $totalResultados): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO cnae_searches (user_id, filters, results_count) VALUES (:user_id, :filters, :results)');
        $stmt->execute([
            ':user_id' => $userId,
            ':filters' => json_encode($filtros, JSON_UNESCAPED_UNICODE),
            ':results' => $totalResultados,
        ]);
    }

    /**
     * Retorna o historico de buscas do usuario, limitado pelo parametro.
     *
     * @return array<int, array{filters: array, results_count: int, created_at: string}>
     */
    public static function listar(int $userId, int $limite = 5): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT filters, results_count, created_at FROM cnae_searches WHERE user_id = :user ORDER BY created_at DESC LIMIT :limite');
        $stmt->bindValue(':user', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        $historicos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $filtros = json_decode($row['filters'] ?? '[]', true);
            if (!is_array($filtros)) {
                $filtros = [];
            }
            $historicos[] = [
                'filters' => $filtros,
                'results_count' => (int) ($row['results_count'] ?? 0),
                'created_at' => $row['created_at'] ?? '',
            ];
        }

        return $historicos;
    }
}
