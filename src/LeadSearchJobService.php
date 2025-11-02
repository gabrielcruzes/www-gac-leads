<?php
/**
 * src/LeadSearchJobService.php
 *
 * Gerencia filas de buscas ass�ncronas de leads.
 */

namespace App;

use DateTime;
use PDO;

require_once __DIR__ . '/Database.php';

class LeadSearchJobService
{
    /**
     * Cria um novo job de busca de leads.
     */
    public static function criarJob(int $userId, array $filtros, int $quantidade): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO lead_search_jobs (user_id, filters, quantity) VALUES (:user_id, :filters, :quantity)');
        $stmt->execute([
            ':user_id' => $userId,
            ':filters' => json_encode($filtros, JSON_UNESCAPED_UNICODE),
            ':quantity' => $quantidade,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Busca um job pertencente ao usu�rio.
     */
    public static function buscarJobDoUsuario(int $jobId, int $userId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM lead_search_jobs WHERE id = :id AND user_id = :user');
        $stmt->execute([
            ':id' => $jobId,
            ':user' => $userId,
        ]);

        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            return null;
        }

        return self::normalizarJob($job);
    }

    /**
     * Busca um job em aberto (pendente/processando ou conclu�do sem entrega) para o usu�rio.
     */
    public static function buscarJobEmAberto(int $userId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT * FROM lead_search_jobs
             WHERE user_id = :user
               AND (status IN ('pending','processing','failed') OR (status = 'completed' AND delivered_at IS NULL))
             ORDER BY created_at ASC
             LIMIT 1"
        );
        $stmt->execute([':user' => $userId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        return $job ? self::normalizarJob($job) : null;
    }

    /**
     * Busca o pr�ximo job pendente e marca como em processamento.
     */
    public static function puxarJobPendenteParaProcessamento(): ?array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        $stmt = $pdo->query("SELECT * FROM lead_search_jobs WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1 FOR UPDATE");
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            $pdo->commit();
            return null;
        }

        $update = $pdo->prepare("UPDATE lead_search_jobs SET status = 'processing', progress = 0, updated_at = NOW() WHERE id = :id");
        $update->execute([':id' => $job['id']]);

        $pdo->commit();

        $job['status'] = 'processing';
        $job['progress'] = 0;

        return self::normalizarJob($job);
    }

    /**
     * Atualiza o progresso de um job.
     */
    public static function atualizarProgresso(int $jobId, int $progresso): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE lead_search_jobs SET progress = :progress, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':progress' => max(0, min(100, $progresso)),
            ':id' => $jobId,
        ]);
    }

    /**
     * Marca um job como conclu�do e salva os resultados.
     *
     * @param array<int, mixed> $leads
     */
    public static function concluirJob(int $jobId, array $leads): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE lead_search_jobs
             SET status = :status,
                 progress = 100,
                 results = :results,
                 completed_at = NOW(),
                 updated_at = NOW(),
                 error_message = NULL
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => 'completed',
            ':results' => json_encode($leads, JSON_UNESCAPED_UNICODE),
            ':id' => $jobId,
        ]);
    }

    /**
     * Marca um job como falho.
     */
    public static function falharJob(int $jobId, string $mensagem): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE lead_search_jobs
             SET status = :status,
                 progress = 0,
                 error_message = :erro,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => 'failed',
            ':erro' => $mensagem,
            ':id' => $jobId,
        ]);
    }

    /**
     * Marca um job como entregue ao usu�rio.
     */
    public static function marcarEntregue(int $jobId): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE lead_search_jobs
             SET delivered_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':id' => $jobId]);
    }

    /**
     * Recupera um job independente do usu�rio (utilizado pelo cron).
     */
    public static function buscarJob(int $jobId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM lead_search_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        return $job ? self::normalizarJob($job) : null;
    }

    /**
     * Normaliza os dados do job (decodifica JSON e padroniza datas).
     */
    private static function normalizarJob(array $job): array
    {
        $job['filters'] = self::decodificarJson($job['filters'] ?? '[]');
        $job['results'] = self::decodificarJson($job['results'] ?? '[]');

        $job['id'] = isset($job['id']) ? (int) $job['id'] : null;
        $job['user_id'] = isset($job['user_id']) ? (int) $job['user_id'] : null;
        $job['quantity'] = isset($job['quantity']) ? (int) $job['quantity'] : 0;
        $job['progress'] = isset($job['progress']) ? (int) $job['progress'] : 0;

        foreach (['created_at', 'updated_at', 'completed_at', 'delivered_at'] as $campo) {
            if (!empty($job[$campo]) && !$job[$campo] instanceof DateTime) {
                $job[$campo] = new DateTime($job[$campo]);
            }
        }

        return $job;
    }

    /**
     * @return array<mixed>
     */
    private static function decodificarJson(?string $conteudo): array
    {
        if ($conteudo === null || $conteudo === '') {
            return [];
        }

        $decodificado = json_decode($conteudo, true);

        return is_array($decodificado) ? $decodificado : [];
    }
}
