<?php
/**
 * src/ExportService.php
 *
 * Responsável pela geração e histórico de exportações em CSV.
 */

namespace App;

use DateTime;

require_once __DIR__ . '/Database.php';

class ExportService
{
    /**
     * Gera um arquivo CSV com os leads e registra a exportação.
     */
    public static function gerarCsv(int $userId, string $segmento, array $leads): ?string
    {
        if (empty($leads)) {
            return null;
        }

        $leads = self::normalizarLeads($leads);

        $exportDir = __DIR__ . '/../storage/exports';
        if (!is_dir($exportDir) && !mkdir($exportDir, 0775, true) && !is_dir($exportDir)) {
            return null;
        }

        $timestamp = (new DateTime())->format('Ymd_His');
        $filename = sprintf('export_%d_%s.csv', $userId, $timestamp);
        $filePath = $exportDir . '/' . $filename;

        $headers = self::extrairCabecalho($leads);

        $handle = fopen($filePath, 'w');
        if ($handle === false) {
            return null;
        }

        fputcsv($handle, $headers, ';');
        foreach ($leads as $lead) {
            $row = [];
            foreach ($headers as $column) {
                $row[] = self::formatarValor($lead[$column] ?? null);
            }
            fputcsv($handle, $row, ';');
        }
        fclose($handle);

        $relativePath = 'storage/exports/' . $filename;

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO exports (user_id, segment, quantity, file_path) VALUES (:user_id, :segment, :quantity, :file_path)');
        $stmt->execute([
            ':user_id' => $userId,
            ':segment' => $segmento,
            ':quantity' => count($leads),
            ':file_path' => $relativePath,
        ]);

        return $relativePath;
    }

    /**
     * Retorna o histórico de exportações do usuário.
     */
    public static function listarHistorico(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM exports WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Recupera uma exporta��ǜo especifica do usu��rio.
     */
    public static function obterExportacao(int $userId, int $exportId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM exports WHERE user_id = :user_id AND id = :id LIMIT 1');
        $stmt->execute([
            ':user_id' => $userId,
            ':id' => $exportId,
        ]);

        $export = $stmt->fetch();

        return $export !== false ? $export : null;
    }

    /**
     * Extrai dinamicamente as colunas que farão parte do CSV.
     */
    private static function extrairCabecalho(array $leads): array
    {
        $headers = [];
        foreach ($leads as $lead) {
            foreach ($lead as $key => $value) {
                if (!in_array($key, $headers, true)) {
                    $headers[] = $key;
                }
            }
        }

        return $headers;
    }

    /**
     * Normaliza os dados dos leads expandindo as chaves de detalhes.
     *
     * @param array<int, array<string, mixed>> $leads
     * @return array<int, array<string, mixed>>
     */
    private static function normalizarLeads(array $leads): array
    {
        return array_map(static function (array $lead): array {
            if (array_key_exists('item_id', $lead)) {
                unset($lead['item_id']);
            }

            if (isset($lead['detalhes']) && is_array($lead['detalhes'])) {
                $detalhesPlanos = [];
                self::achatarDetalhes($lead['detalhes'], 'detalhes', $detalhesPlanos);
                unset($lead['detalhes']);
                $lead = array_merge($lead, $detalhesPlanos);
            } else {
                unset($lead['detalhes']);
            }

            foreach ($lead as $chave => $valor) {
                if (is_array($valor) || is_object($valor)) {
                    $lead[$chave] = json_encode($valor, JSON_UNESCAPED_UNICODE);
                }
            }

            return $lead;
        }, $leads);
    }

    /**
     * Constrói um mapa de colunas a partir de uma estrutura arbitrária.
     *
     * @param mixed $valor
     */
    private static function achatarDetalhes($valor, string $prefixo, array &$destino): void
    {
        if (is_array($valor) || is_object($valor)) {
            $valor = (array) $valor;
            if ($valor === []) {
                $destino[$prefixo] = '';
                return;
            }

            foreach ($valor as $chave => $conteudo) {
                $parte = is_int($chave) ? (string) $chave : (string) $chave;
                $novoPrefixo = $prefixo !== '' ? $prefixo . '_' . $parte : $parte;
                self::achatarDetalhes($conteudo, $novoPrefixo, $destino);
            }

            return;
        }

        $destino[$prefixo] = $valor;
    }

    /**
     * Normaliza o valor para formato textual utilizado no CSV.
     *
     * @param mixed $valor
     */
    private static function formatarValor($valor): string
    {
        if ($valor === null) {
            return '';
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        if (is_scalar($valor)) {
            return (string) $valor;
        }

        return json_encode($valor, JSON_UNESCAPED_UNICODE);
    }
}
