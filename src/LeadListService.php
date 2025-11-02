<?php
/**
 * src/LeadListService.php
 *
 * Gerencia as listas de leads criadas pelos usuarios.
 */

namespace App;

use PDO;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/LeadService.php';

class LeadListService
{
    /**
     * Retorna todas as listas do usuario.
     */
    public static function listarListas(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT l.*, COUNT(i.id) AS total_items FROM lead_lists l LEFT JOIN lead_list_items i ON i.lead_list_id = l.id AND i.user_id = l.user_id WHERE l.user_id = :user GROUP BY l.id ORDER BY l.created_at DESC');
        $stmt->execute([':user' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Cria uma nova lista e retorna o ID gerado.
     */
    public static function criarLista(int $userId, string $nome): ?int
    {
        $nome = trim($nome);
        if ($nome === '') {
            return null;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO lead_lists (user_id, name) VALUES (:user, :name)');

        if (!$stmt->execute([':user' => $userId, ':name' => $nome])) {
            return null;
        }

        return (int) $pdo->lastInsertId();
    }

    /**
     * Recupera os metadados de uma lista pertencente ao usuario.
     */
    public static function obterLista(int $userId, int $listaId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM lead_lists WHERE id = :id AND user_id = :user LIMIT 1');
        $stmt->execute([':id' => $listaId, ':user' => $userId]);

        $lista = $stmt->fetch(PDO::FETCH_ASSOC);

        return $lista ?: null;
    }

    /**
     * Lista os itens de uma lista ordenados por criacao.
     */
    public static function listarItens(int $userId, int $listaId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM lead_list_items WHERE lead_list_id = :lista AND user_id = :user ORDER BY created_at DESC');
        $stmt->execute([':lista' => $listaId, ':user' => $userId]);

        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $leads = [];
        foreach ($registros as $item) {
            $dados = json_decode($item['summary'], true) ?: [];
            $dados['detalhes'] = json_decode($item['data'], true) ?: [];
            $dados['item_id'] = (int) $item['id'];
            $leads[] = $dados;
        }

        return $leads;
    }

    /**
     * Adiciona um lead a lista, consumindo um credito quando necessario.
     *
     * @return array{success:bool, message?:string, credits?:int}
     */
    public static function adicionarLead(int $userId, int $listaId, string $leadToken): array
    {
        $lista = self::obterLista($userId, $listaId);
        if (!$lista) {
            return ['success' => false, 'message' => 'Lista nao encontrada.'];
        }

        $leadResultado = LeadService::visualizarLead($leadToken);
        if (!$leadResultado) {
            return ['success' => false, 'message' => 'Lead indisponivel.'];
        }

        if (isset($leadResultado['error'])) {
            return ['success' => false, 'message' => $leadResultado['error']];
        }

        $leadData = $leadResultado['data'] ?? [];
        $summary = $leadResultado['summary'] ?? LeadService::resumirLead($leadData);

        $cnpjNumerico = $summary['cnpj_raw'] ?? self::normalizarCnpj($summary['cnpj'] ?? null);

        // Evita duplicacao de leads na mesma lista.
        if ($cnpjNumerico) {
            $pdo = Database::getConnection();
            $stmtCheck = $pdo->prepare('SELECT id FROM lead_list_items WHERE lead_list_id = :lista AND user_id = :user AND cnpj = :cnpj LIMIT 1');
            $stmtCheck->execute([
                ':lista' => $listaId,
                ':user' => $userId,
                ':cnpj' => $cnpjNumerico,
            ]);
            if ($stmtCheck->fetch(PDO::FETCH_ASSOC)) {
                return ['success' => false, 'message' => 'Este lead ja foi adicionado a lista.'];
            }
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO lead_list_items (lead_list_id, user_id, lead_id, cnpj, summary, data) VALUES (:lista, :user, :lead_id, :cnpj, :summary, :data)');
        $stmt->execute([
            ':lista' => $listaId,
            ':user' => $userId,
            ':lead_id' => $leadResultado['id'] ?? null,
            ':cnpj' => $cnpjNumerico,
            ':summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
            ':data' => json_encode($leadData, JSON_UNESCAPED_UNICODE),
        ]);

        return [
            'success' => true,
            'credits' => $leadResultado['credits'] ?? null,
            'message' => 'Lead adicionado com sucesso!',
        ];
    }

    /**
     * Normaliza o CNPJ removendo caracteres nao numericos.
     */
    private static function normalizarCnpj(?string $cnpj): ?string
    {
        if (!$cnpj) {
            return null;
        }

        $numeros = preg_replace('/\D/', '', $cnpj);

        return $numeros ?: null;
    }
}
