<?php
/**
 * src/LeadService.php
 *
 * Regras de negocio para busca e consumo de leads B2B.
 */

namespace App;

use PDO;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CasaDosDadosApi.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/LeadListService.php';

class LeadService
{
    /**
     * Busca leads via API da Casa dos Dados e armazena na sessao para consumo posterior.
     *
     * @param array $filtros
     * @return array{leads:array<int,array<string,mixed>>,total:int,has_more:bool}
     */
    public static function buscarLeads(array $filtros): array
    {
        $api = new CasaDosDadosApi();
        $resultadoApi = $api->buscarLeads($filtros);

        $leadsApi = $resultadoApi['leads'] ?? [];
        $armazenados = self::armazenarLeadsNaSessao($leadsApi, $filtros);

        if (!empty($filtros['somente_sem_lista'])) {
            $armazenados = array_values(array_filter($armazenados, static function ($lead) {
                return empty($lead['already_imported']);
            }));
        }

        $limite = isset($filtros['quantidade']) ? (int) $filtros['quantidade'] : count($leadsApi);
        $hasMore = (bool) ($resultadoApi['has_more'] ?? (count($leadsApi) >= $limite));
        if (!empty($filtros['somente_sem_lista'])) {
            $hasMore = false;
        }

        $totalRetornado = (int) ($resultadoApi['total'] ?? count($armazenados));
        if (!empty($filtros['somente_sem_lista'])) {
            $totalRetornado = count($armazenados);
        }

        return [
            'leads' => $armazenados,
            'total' => $totalRetornado,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Persiste os leads recuperados na sess�o e retorna os resumos com tokens.
     *
     * @param array<int, array<string,mixed>> $leads
     * @param array<string,mixed> $filtros
     * @return array<int, array<string,mixed>>
     */
    public static function armazenarLeadsNaSessao(array $leads, array $filtros): array
    {
        if (!isset($_SESSION['lead_results'])) {
            $_SESSION['lead_results'] = [];
        }

        $usuario = Auth::user();
        $userId = isset($usuario['id']) ? (int) $usuario['id'] : null;

        $cnpjsParaConsulta = [];
        foreach ($leads as $leadBruto) {
            $cnpjExtraido = $leadBruto['cnpj_raw'] ?? preg_replace('/\D/', '', $leadBruto['cnpj'] ?? '');
            if (!empty($cnpjExtraido)) {
                $cnpjsParaConsulta[] = $cnpjExtraido;
            }
        }

        $leadsJaImportados = [];
        if ($userId && $cnpjsParaConsulta) {
            try {
                $leadsJaImportados = LeadListService::buscarItensPorCnpjs($userId, $cnpjsParaConsulta);
            } catch (\Throwable $e) {
                error_log('Falha ao verificar leads ja importados: ' . $e->getMessage());
            }
        }

        $segmentoPadrao = $filtros['segmento_label'] ?? ($filtros['cnae'] ?? 'Segmento nao informado');
        $resultados = [];

        foreach ($leads as $lead) {
            $token = uniqid('lead_', true);

            $lead['segmento'] = $lead['segmento'] ?? $segmentoPadrao;
            unset($lead['_raw']);
            $cnpjRaw = $lead['cnpj_raw'] ?? preg_replace('/\D/', '', $lead['cnpj'] ?? '');
            $cnpjRaw = $cnpjRaw ?: null;
            $leadExistente = $cnpjRaw ? ($leadsJaImportados[$cnpjRaw] ?? null) : null;

            $_SESSION['lead_results'][$token] = [
                'summary' => $lead,
                'segmento' => $lead['segmento'],
                'cnpj' => $cnpjRaw,
                'data' => null, // sera preenchido quando o lead for consumido
                'consumed' => false,
            ];

            if ($leadExistente) {
                $_SESSION['lead_results'][$token]['existing_item'] = $leadExistente;
                if (!empty($leadExistente['lead_id'])) {
                    $_SESSION['lead_results'][$token]['db_id'] = (int) $leadExistente['lead_id'];
                }

                $summaryAtual = $_SESSION['lead_results'][$token]['summary'] ?? [];
                if (!empty($leadExistente['data'])) {
                    $_SESSION['lead_results'][$token]['data'] = $leadExistente['data'];
                    $summaryAtual = self::resumirLead($leadExistente['data'], $summaryAtual);
                }
                if (!empty($leadExistente['summary'])) {
                    $summaryAtual = array_merge($summaryAtual, $leadExistente['summary']);
                }
                $_SESSION['lead_results'][$token]['summary'] = $summaryAtual;
            }

            $leadResumo = $_SESSION['lead_results'][$token]['summary'] ?? $lead;
            if ($leadExistente && !empty($_SESSION['lead_results'][$token]['data'])) {
                $leadResumo = array_merge($leadResumo, $_SESSION['lead_results'][$token]['summary'] ?? []);
            }
            $leadComToken = array_merge($lead, $leadResumo);
            $leadComToken['token'] = $token;
            $leadComToken['already_imported'] = (bool) $leadExistente;
            $leadComToken['imported_lists'] = $leadExistente['list_names'] ?? [];
            $resultados[] = $leadComToken;
        }

        return $resultados;
    }

    /**
     * Consome um lead especifico, debitando creditos e registrando no banco.
     */
    public static function visualizarLead(string $leadId): ?array
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        if (isset($_SESSION['lead_results'][$leadId])) {
            return self::consumirLeadDaSessao($user['id'], $leadId);
        }

        if (ctype_digit($leadId)) {
            return self::buscarLeadPersistido((int) $leadId, (int) $user['id']);
        }

        return null;
    }

    /**
     * Consome um lead previamente armazenado na sessao.
     */
    private static function consumirLeadDaSessao(int $userId, string $leadToken): ?array
    {
        $entrada = &$_SESSION['lead_results'][$leadToken];

        if (!empty($entrada['existing_item'])) {
            return self::retornarLeadExistenteDaSessao($userId, $entrada);
        }

        // Se ja consumido anteriormente, retorna os dados persistidos.
        if (!empty($entrada['consumed']) && isset($entrada['db_id'])) {
            return self::buscarLeadPersistido((int) $entrada['db_id'], $userId);
        }

        $cnpj = $entrada['cnpj'] ?? null;
        $summaryAnterior = $entrada['summary'] ?? [];

        if (empty($entrada['data'])) {
            if (!$cnpj) {
                return ['error' => 'Nao foi possivel identificar o CNPJ do lead.'];
            }

            $api = new CasaDosDadosApi();
            $detalhes = $api->detalharCnpj($cnpj);

            if (empty($detalhes)) {
                return ['error' => 'Nao foi possivel obter os detalhes completos da empresa.'];
            }

            $entrada['data'] = $detalhes;
            $entrada['summary'] = self::resumirLead($detalhes, $summaryAnterior);
        }

        $leadData = $entrada['data'];
        $summaryAtual = $entrada['summary'] ?? self::resumirLead($leadData, $summaryAnterior);

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        // Bloqueia o registro do usuario para evitar concorrencia na deducao de creditos.
        $stmt = $pdo->prepare('SELECT credits FROM users WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int) $row['credits'] < LEAD_VIEW_COST) {
            $pdo->rollBack();
            return ['error' => 'Creditos insuficientes'];
        }

        $novoSaldo = (int) $row['credits'] - LEAD_VIEW_COST;

        $stmtUpdate = $pdo->prepare('UPDATE users SET credits = :credits WHERE id = :id');
        $stmtUpdate->execute([
            ':credits' => $novoSaldo,
            ':id' => $userId,
        ]);

        $stmtLead = $pdo->prepare('INSERT INTO leads (user_id, source, data) VALUES (:user_id, :source, :data)');
        $stmtLead->execute([
            ':user_id' => $userId,
            ':source' => 'casa_dos_dados',
            ':data' => json_encode($leadData, JSON_UNESCAPED_UNICODE),
        ]);
        $leadDbId = (int) $pdo->lastInsertId();

        $pdo->commit();

        $entrada['consumed'] = true;
        $entrada['db_id'] = $leadDbId;
        $entrada['summary'] = $summaryAtual;

        try {
            LeadListService::armazenarLeadVisualizado($userId, $leadDbId, $summaryAtual, $leadData);
        } catch (\Throwable $e) {
            error_log('Nao foi possivel registrar o lead consumido na lista padrao: ' . $e->getMessage());
        }

        return [
            'id' => $leadDbId,
            'data' => $leadData,
            'summary' => $summaryAtual,
            'credits' => $novoSaldo,
        ];
    }

    /**
     * Retorna dados de um lead que ja foi importado anteriormente sem debitar creditos.
     *
     * @param array<string,mixed> $entrada
     * @return array<string,mixed>|null
     */
    private static function retornarLeadExistenteDaSessao(int $userId, array &$entrada): ?array
    {
        $existente = $entrada['existing_item'] ?? [];
        $leadId = isset($existente['lead_id']) ? (int) $existente['lead_id'] : null;
        $listas = $existente['list_names'] ?? [];

        if ($leadId) {
            $persistido = self::buscarLeadPersistido($leadId, $userId);
            if ($persistido) {
                $entrada['data'] = $persistido['data'] ?? [];
                $entrada['summary'] = $persistido['summary'] ?? [];
                $entrada['consumed'] = true;
                $entrada['db_id'] = $persistido['id'] ?? $leadId;
                $entrada['existing_item']['data'] = $entrada['data'];
                $entrada['existing_item']['summary'] = $entrada['summary'];

                $persistido['already_imported'] = true;
                $persistido['imported_lists'] = $listas;

                return $persistido;
            }
        }

        $dados = $entrada['data'] ?? ($existente['data'] ?? []);
        $summary = $entrada['summary'] ?? ($existente['summary'] ?? []);
        if ($dados) {
            $summary = self::resumirLead($dados, $summary);
        }

        $entrada['data'] = $dados;
        $entrada['summary'] = $summary;
        $entrada['consumed'] = true;
        if ($leadId) {
            $entrada['db_id'] = $leadId;
        }

        return [
            'id' => $leadId,
            'data' => $dados,
            'summary' => $summary,
            'credits' => self::buscarCreditosAtuais($userId),
            'already_imported' => true,
            'imported_lists' => $listas,
        ];
    }

    /**
     * Recupera um lead ja persistido para visualizacao futura.
     */
    private static function buscarLeadPersistido(int $leadId, int $userId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM leads WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            ':id' => $leadId,
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $leadData = json_decode($row['data'], true) ?? [];
        $summary = self::resumirLead($leadData);

        try {
            LeadListService::armazenarLeadVisualizado($userId, (int) $row['id'], $summary, $leadData);
        } catch (\Throwable $e) {
            error_log('Nao foi possivel sincronizar o lead persistido com a lista padrao: ' . $e->getMessage());
        }

        $credits = self::buscarCreditosAtuais($userId);

        return [
            'id' => (int) $row['id'],
            'data' => $leadData,
            'summary' => $summary,
            'credits' => $credits,
        ];
    }

    /**
     * Consulta o saldo de creditos mais recente do usuario.
     */
    private static function buscarCreditosAtuais(int $userId): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT credits FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['credits'] : 0;
    }

    /**
     * Extrai um resumo padrao de dados de lead para exibicao.
     */
    public static function resumirLead(array $dados, array $fallback = []): array
    {
        $cnpj = $dados['cnpj'] ?? ($dados['numero_cnpj'] ?? null);
        if (is_array($cnpj)) {
            $cnpj = $cnpj['numero'] ?? $cnpj['cnpj'] ?? null;
        }

        $emails = $dados['emails'] ?? $dados['email'] ?? [];
        $telefones = $dados['telefones'] ?? $dados['telefone'] ?? [];

        $email = is_array($emails) ? reset($emails) : $emails;
        if (is_array($email)) {
            $email = $email['email'] ?? reset($email);
        }

        $telefone = is_array($telefones) ? reset($telefones) : $telefones;
        if (is_array($telefone)) {
            $telefone = $telefone['numero'] ?? $telefone['telefone'] ?? reset($telefone);
        }

        $cnpjRaw = $cnpj ? preg_replace('/\D/', '', $cnpj) : ($fallback['cnpj_raw'] ?? null);
        $cnpjFormatado = $cnpjRaw ? self::formatarCnpj($cnpjRaw) : ($fallback['cnpj'] ?? '-');

        $segmento = $dados['segmento'] ?? ($fallback['segmento'] ?? null);
        if (!$segmento && isset($dados['atividade_principal']['codigo'])) {
            $codigoAtividade = $dados['atividade_principal']['codigo'];
            $descricaoAtividade = $dados['atividade_principal']['descricao'] ?? '';
            $segmento = trim($codigoAtividade . ' - ' . $descricaoAtividade);
        }
        if (!$segmento) {
            $segmento = $fallback['segmento'] ?? 'Segmento nao informado';
        }

        $resultado = [
            'empresa' => $dados['razao_social'] ?? $dados['nome_fantasia'] ?? $dados['empresa'] ?? ($fallback['empresa'] ?? 'Empresa nao informada'),
            'segmento' => $segmento,
            'cnpj' => $cnpjRaw ?: ($fallback['cnpj'] ?? '-'),
            'cnpj_formatado' => $cnpjFormatado,
            'cnpj_raw' => $cnpjRaw,
            'email' => $email ?: ($fallback['email'] ?? '-'),
            'telefone' => $telefone ?: ($fallback['telefone'] ?? '-'),
            'cidade' => $dados['municipio'] ?? $dados['endereco_municipio'] ?? $dados['cidade'] ?? ($fallback['cidade'] ?? '-'),
            'uf' => $dados['uf'] ?? $dados['endereco_uf'] ?? $dados['cidade_uf'] ?? ($fallback['uf'] ?? '-'),
            'situacao' => $dados['situacao_cadastral']['situacao_atual'] ?? ($fallback['situacao'] ?? '-'),
        ];

        return array_merge($fallback, $resultado);
    }

    private static function formatarCnpj(string $cnpj): string
    {
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2)
        );
    }
}
