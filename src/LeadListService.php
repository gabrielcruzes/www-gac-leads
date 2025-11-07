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
    public const DEFAULT_LIST_NAME = 'Sem lista';

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
     * Retorna informacoes dos itens ja existentes para os CNPJs informados.
     *
     * @return array<string,array{lead_id:?int,summary:array<string,mixed>,data:array<string,mixed>,list_names:array<int,string>}>
     */
    public static function buscarItensPorCnpjs(int $userId, array $cnpjs): array
    {
        $cnpjNumericos = [];
        foreach ($cnpjs as $cnpj) {
            if (is_array($cnpj)) {
                $cnpj = $cnpj['cnpj'] ?? ($cnpj['cnpj_raw'] ?? null);
            }
            $normalizado = self::normalizarCnpj(is_string($cnpj) ? $cnpj : null);
            if ($normalizado) {
                $cnpjNumericos[$normalizado] = $normalizado;
            }
        }

        if (!$cnpjNumericos) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($cnpjNumericos), '?'));
        $params = array_merge([$userId], array_values($cnpjNumericos));

        $pdo = Database::getConnection();
        $sql = <<<SQL
SELECT
    i.cnpj,
    MAX(i.lead_id) AS lead_id,
    MAX(i.summary) AS summary_json,
    MAX(i.data) AS data_json,
    GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR '||') AS listas
FROM lead_list_items i
INNER JOIN lead_lists l ON l.id = i.lead_list_id
WHERE i.user_id = ?
  AND i.cnpj IN ($placeholders)
GROUP BY i.cnpj
SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) {
            return [];
        }

        $agrupados = [];
        foreach ($rows as $row) {
            $cnpj = (string) ($row['cnpj'] ?? '');
            if ($cnpj === '') {
                continue;
            }

            $summary = [];
            if (!empty($row['summary_json'])) {
                $decoded = json_decode($row['summary_json'], true);
                if (is_array($decoded)) {
                    $summary = $decoded;
                }
            }

            $data = [];
            if (!empty($row['data_json'])) {
                $decoded = json_decode($row['data_json'], true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }

            $listas = [];
            if (!empty($row['listas'])) {
                $listas = array_values(array_filter(array_map('trim', explode('||', $row['listas'])), static function ($item) {
                    return $item !== '';
                }));
            }

            $agrupados[$cnpj] = [
                'lead_id' => $row['lead_id'] !== null ? (int) $row['lead_id'] : null,
                'summary' => $summary,
                'data' => $data,
                'list_names' => $listas,
            ];
        }

        return $agrupados;
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
     * Garante que a lista padrao "Sem lista" exista para o usuario e a retorna.
     *
     * @return array<string,mixed>|null
     */
    public static function obterOuCriarListaPadrao(int $userId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM lead_lists WHERE user_id = :user AND name = :name LIMIT 1');
        $stmt->execute([
            ':user' => $userId,
            ':name' => self::DEFAULT_LIST_NAME,
        ]);

        $lista = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($lista) {
            return $lista;
        }

        $stmtInsert = $pdo->prepare('INSERT INTO lead_lists (user_id, name) VALUES (:user, :name)');
        if (!$stmtInsert->execute([
            ':user' => $userId,
            ':name' => self::DEFAULT_LIST_NAME,
        ])) {
            return null;
        }

        $novoId = (int) $pdo->lastInsertId();

        $stmtBusca = $pdo->prepare('SELECT * FROM lead_lists WHERE id = :id AND user_id = :user LIMIT 1');
        $stmtBusca->execute([
            ':id' => $novoId,
            ':user' => $userId,
        ]);

        $listaCriada = $stmtBusca->fetch(PDO::FETCH_ASSOC);

        return $listaCriada ?: null;
    }

    /**
     * Atualiza o nome de uma lista existente do usuario.
     */
    public static function renomearLista(int $userId, int $listaId, string $novoNome): bool
    {
        $novoNome = trim($novoNome);
        if ($novoNome === '') {
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE lead_lists SET name = :name WHERE id = :id AND user_id = :user');
        $executado = $stmt->execute([
            ':name' => $novoNome,
            ':id' => $listaId,
            ':user' => $userId,
        ]);

        return $executado;
    }

    /**
     * Remove uma lista do usuario movendo os itens para a lista padrao.
     */
    public static function removerLista(int $userId, int $listaId): bool
    {
        if ($listaId <= 0) {
            return false;
        }

        $lista = self::obterLista($userId, $listaId);
        if (!$lista) {
            return false;
        }

        $listaPadrao = self::obterOuCriarListaPadrao($userId);
        if (!$listaPadrao || (int) $listaPadrao['id'] === $listaId) {
            return false;
        }

        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $stmtMover = $pdo->prepare('UPDATE lead_list_items SET lead_list_id = :destino WHERE user_id = :user AND lead_list_id = :lista');
            $stmtMover->execute([
                ':destino' => (int) $listaPadrao['id'],
                ':user' => $userId,
                ':lista' => $listaId,
            ]);

            $stmtDelete = $pdo->prepare('DELETE FROM lead_lists WHERE id = :id AND user_id = :user');
            $stmtDelete->execute([
                ':id' => $listaId,
                ':user' => $userId,
            ]);

            $pdo->commit();

            return $stmtDelete->rowCount() > 0;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erro ao remover lista: ' . $exception->getMessage());

            return false;
        }
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
        $pdo = Database::getConnection();

        $itemExistente = null;
        if ($cnpjNumerico) {
            $stmtCheck = $pdo->prepare('SELECT id, lead_list_id FROM lead_list_items WHERE user_id = :user AND cnpj = :cnpj LIMIT 1');
            $stmtCheck->execute([
                ':user' => $userId,
                ':cnpj' => $cnpjNumerico,
            ]);
            $registro = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($registro) {
                $itemExistente = $registro;
                if ((int) $registro['lead_list_id'] === $listaId) {
                    return ['success' => false, 'message' => 'Este lead ja foi adicionado a lista.'];
                }
            }
        }

        $params = [
            ':lista' => $listaId,
            ':user' => $userId,
            ':lead_id' => $leadResultado['id'] ?? null,
            ':cnpj' => $cnpjNumerico,
            ':summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
            ':data' => json_encode($leadData, JSON_UNESCAPED_UNICODE),
        ];

        if ($itemExistente) {
            $params[':id'] = (int) $itemExistente['id'];
            $stmtAtualiza = $pdo->prepare('UPDATE lead_list_items SET lead_list_id = :lista, lead_id = :lead_id, cnpj = :cnpj, summary = :summary, data = :data WHERE id = :id AND user_id = :user');
            $stmtAtualiza->execute($params);
        } else {
            $stmt = $pdo->prepare('INSERT INTO lead_list_items (lead_list_id, user_id, lead_id, cnpj, summary, data) VALUES (:lista, :user, :lead_id, :cnpj, :summary, :data)');
            $stmt->execute($params);
        }

        return [
            'success' => true,
            'credits' => $leadResultado['credits'] ?? null,
            'message' => 'Lead adicionado com sucesso!',
        ];
    }

    /**
     * Registra automaticamente um lead consumido na pasta padrao "Sem lista".
     */
    public static function armazenarLeadVisualizado(int $userId, int $leadId, array $summary, array $leadData): void
    {
        $cnpjNumerico = $summary['cnpj_raw'] ?? self::normalizarCnpj($summary['cnpj'] ?? null);

        $pdo = Database::getConnection();

        $summaryJson = json_encode($summary, JSON_UNESCAPED_UNICODE);
        $dataJson = json_encode($leadData, JSON_UNESCAPED_UNICODE);

        $registroExistente = null;
        if ($cnpjNumerico) {
            $stmtBusca = $pdo->prepare('SELECT id FROM lead_list_items WHERE user_id = :user AND cnpj = :cnpj LIMIT 1');
            $stmtBusca->execute([
                ':user' => $userId,
                ':cnpj' => $cnpjNumerico,
            ]);
            $registroExistente = $stmtBusca->fetch(PDO::FETCH_ASSOC);
        }

        if (!$registroExistente && $leadId > 0) {
            $stmtBuscaId = $pdo->prepare('SELECT id FROM lead_list_items WHERE user_id = :user AND lead_id = :lead LIMIT 1');
            $stmtBuscaId->execute([
                ':user' => $userId,
                ':lead' => $leadId,
            ]);
            $registroExistente = $stmtBuscaId->fetch(PDO::FETCH_ASSOC);
        }

        if ($registroExistente) {
            $itemId = (int) $registroExistente['id'];
            $stmtAtualiza = $pdo->prepare('UPDATE lead_list_items SET lead_id = :lead, cnpj = :cnpj, summary = :summary, data = :data WHERE id = :id AND user_id = :user');
            $stmtAtualiza->execute([
                ':lead' => $leadId,
                ':cnpj' => $cnpjNumerico,
                ':summary' => $summaryJson,
                ':data' => $dataJson,
                ':id' => $itemId,
                ':user' => $userId,
            ]);

            return;
        }

        $listaPadrao = self::obterOuCriarListaPadrao($userId);
        if (!$listaPadrao) {
            return;
        }

        $listaId = (int) $listaPadrao['id'];

        $stmtInsere = $pdo->prepare('INSERT INTO lead_list_items (lead_list_id, user_id, lead_id, cnpj, summary, data) VALUES (:lista, :user, :lead, :cnpj, :summary, :data)');
        $stmtInsere->execute([
            ':lista' => $listaId,
            ':user' => $userId,
            ':lead' => $leadId,
            ':cnpj' => $cnpjNumerico,
            ':summary' => $summaryJson,
            ':data' => $dataJson,
        ]);
    }

    /**
     * Move um item de lead para outra lista do usuario.
     */
    public static function moverItem(int $userId, int $itemId, int $listaDestinoId): bool
    {
        if ($itemId <= 0 || $listaDestinoId <= 0) {
            return false;
        }

        $listaDestino = self::obterLista($userId, $listaDestinoId);
        if (!$listaDestino) {
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE lead_list_items SET lead_list_id = :lista WHERE id = :id AND user_id = :user');
        $stmt->execute([
            ':lista' => $listaDestinoId,
            ':id' => $itemId,
            ':user' => $userId,
        ]);

        return $stmt->rowCount() > 0;
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
