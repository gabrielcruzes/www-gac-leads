<?php
/**
 * src/CasaDosDadosApi.php
 *
 * Camada de integracao com a API da Casa dos Dados.
 */

namespace App;

require_once __DIR__ . '/Database.php';

class CasaDosDadosApi
{
    private const SEARCH_ENDPOINT = 'https://api.casadosdados.com.br/v5/cnpj/pesquisa';
    private const DETAIL_ENDPOINT = 'https://api.casadosdados.com.br/v4/cnpj/';

    /**
     * Consulta a API por empresas com base nos filtros informados.
     *
     * @return array{leads:array<int,array<string,mixed>>,total:int,has_more:bool,pagina:int,limite:int}
     */
    public function buscarLeads(array $filtros): array
    {
        $payload = $this->montarPayloadPesquisa($filtros);
        $quantidadeSolicitada = max(1, (int) ($filtros['quantidade'] ?? 100));

        $respostaPesquisa = $this->executarRequisicao('POST', self::SEARCH_ENDPOINT, $payload);
        $pesquisaBemSucedida = $respostaPesquisa['status'] >= 200 && $respostaPesquisa['status'] < 300;

        $json = $respostaPesquisa['json'] ?? [];

        $cnpjs = [];
        if (!empty($json['cnpjs']) && is_array($json['cnpjs'])) {
            $cnpjs = $json['cnpjs'];
        }

        $leads = [];
        if (!empty($cnpjs)) {
            $limite = $quantidadeSolicitada > 0 ? $quantidadeSolicitada : count($cnpjs);
            $normalizados = array_slice($cnpjs, 0, $limite);

            foreach ($normalizados as $entrada) {
                $leads[] = $this->montarResumoPesquisa($entrada, $filtros);
            }
        }

        if (!$pesquisaBemSucedida) {
            $mensagemErro = sprintf(
                'Casa dos Dados retornou status %d. Resposta: %s',
                $respostaPesquisa['status'],
                substr((string) ($respostaPesquisa['raw'] ?? ''), 0, 500)
            );

            throw new \RuntimeException($mensagemErro);
        }

        $totalResultados = null;
        $possiveisChaves = ['total', 'total_cnpjs', 'quantidade_total', 'total_encontrados', 'total_encontrado'];
        foreach ($possiveisChaves as $chave) {
            if (isset($json[$chave])) {
                $totalResultados = (int) $json[$chave];
                break;
            }
        }

        if ($totalResultados === null && isset($json['meta']['total'])) {
            $totalResultados = (int) $json['meta']['total'];
        }

        if ($totalResultados === null && isset($json['paginacao']['total'])) {
            $totalResultados = (int) $json['paginacao']['total'];
        }

        if ($totalResultados === null && isset($json['totalResultados'])) {
            $totalResultados = (int) $json['totalResultados'];
        }

        if ($totalResultados === null) {
            if (isset($json['cnpjs']) && is_array($json['cnpjs'])) {
                $totalResultados = count($json['cnpjs']);
            } else {
                $totalResultados = count($leads);
            }
        }

        $paginaAtual = max(1, (int) ($payload['pagina'] ?? 1));
        $limite = max(1, (int) ($payload['limite'] ?? $quantidadeSolicitada));
        $temMais = $totalResultados > ($paginaAtual * $limite);

        return [
            'leads' => $leads,
            'total' => $totalResultados,
            'has_more' => $temMais,
            'pagina' => $paginaAtual,
            'limite' => $limite,
        ];
    }

    /**
     * Busca detalhes completos de um CNPJ especifico.
     */
    public function detalharCnpj(string $cnpj): array
    {
        $cnpjNumerico = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpjNumerico) !== 14) {
            return [];
        }

        $resposta = $this->executarRequisicao('GET', self::DETAIL_ENDPOINT . $cnpjNumerico);

        if ($resposta['status'] >= 200 && $resposta['status'] < 300 && !empty($resposta['json'])) {
            return $resposta['json'];
        }

        return [];
    }

    private function registrarLog(string $endpoint, ?array $payload, ?string $responseBody, int $httpStatus): void
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('INSERT INTO api_logs (endpoint, request_body, response_body, http_status) VALUES (:endpoint, :request, :response, :status)');
            $stmt->execute([
                ':endpoint' => $endpoint,
                ':request' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ':response' => $responseBody,
                ':status' => $httpStatus,
            ]);
        } catch (\Throwable $e) {
            // Evita que falhas de log interrompam o fluxo principal.
        }
    }

    private function montarPayloadPesquisa(array $filtros): array
    {
        $payload = [
            'limite' => max(1, (int) ($filtros['quantidade'] ?? 100)),
            'pagina' => max(1, (int) ($filtros['pagina'] ?? 1)),
            'mais_filtros' => [
                'com_email' => isset($filtros['com_email']) ? (bool) $filtros['com_email'] : true,
                'com_telefone' => isset($filtros['com_telefone']) ? (bool) $filtros['com_telefone'] : false,
                'excluir_email_contab' => isset($filtros['excluir_email_contab']) ? (bool) $filtros['excluir_email_contab'] : false,
                'excluir_empresas_visualizadas' => isset($filtros['excluir_empresas_visualizadas']) ? (bool) $filtros['excluir_empresas_visualizadas'] : false,
                'somente_celular' => isset($filtros['somente_celular']) ? (bool) $filtros['somente_celular'] : false,
                'somente_fixo' => isset($filtros['somente_fixo']) ? (bool) $filtros['somente_fixo'] : false,
                'somente_matriz' => isset($filtros['somente_matriz']) ? (bool) $filtros['somente_matriz'] : false,
                'somente_filial' => isset($filtros['somente_filial']) ? (bool) $filtros['somente_filial'] : false,
            ],
        ];

        $situacoes = $filtros['situacao_cadastral'] ?? ['ATIVA'];
        if (!is_array($situacoes)) {
            $situacoes = [$situacoes];
        }
        $situacoes = array_values(array_filter(array_map('strtoupper', $situacoes)));
        if (!empty($situacoes)) {
            $payload['situacao_cadastral'] = $situacoes;
        }

        if (!empty($filtros['cnae'])) {
            $payload['codigo_atividade_principal'] = [(string) $filtros['cnae']];
        } elseif (!empty($filtros['cnaes'])) {
            $payload['codigo_atividade_principal'] = array_values(array_filter((array) $filtros['cnaes']));
        }

        if (!empty($filtros['uf'])) {
            $ufs = is_array($filtros['uf']) ? $filtros['uf'] : [(string) $filtros['uf']];
            $ufs = array_values(array_filter(array_map(static fn($item) => strtoupper(trim($item)), $ufs)));
            if (!empty($ufs)) {
                $payload['uf'] = $ufs;
            }
        }

        if (!empty($filtros['municipio'])) {
            $municipiosBase = is_array($filtros['municipio']) ? $filtros['municipio'] : preg_split('/[,;\r\n]+/', (string) $filtros['municipio']);
            $municipios = array_values(array_filter(array_map(static fn($item) => strtoupper(trim($item)), $municipiosBase ?? [])));
            if (!empty($municipios)) {
                $payload['municipio'] = $municipios;
            }
        }

        if (!empty($filtros['codigo_atividade_secundaria'])) {
            $payload['codigo_atividade_secundaria'] = array_values($filtros['codigo_atividade_secundaria']);
        }

        if (!empty($filtros['codigo_natureza_juridica'])) {
            $payload['codigo_natureza_juridica'] = array_values($filtros['codigo_natureza_juridica']);
        }

        if (!empty($filtros['bairro'])) {
            $payload['bairro'] = array_values(array_map('strtoupper', $filtros['bairro']));
        }

        if (!empty($filtros['cep'])) {
            $payload['cep'] = array_values($filtros['cep']);
        }

        if (!empty($filtros['cnpj'])) {
            $payload['cnpj'] = array_values($filtros['cnpj']);
        }

        if (!empty($filtros['cnpj_raiz'])) {
            $payload['cnpj_raiz'] = array_values($filtros['cnpj_raiz']);
        }

        if (!empty($filtros['ddd'])) {
            $payload['ddd'] = array_values($filtros['ddd']);
        }

        if (!empty($filtros['telefone'])) {
            $payload['telefone'] = array_values($filtros['telefone']);
        }

        if (!empty($filtros['endereco_numero'])) {
            $payload['endereco_numero'] = array_values($filtros['endereco_numero']);
        }

        if (!empty($filtros['excluir_cnpjs'])) {
            $payload['excluir'] = ['cnpj' => array_values($filtros['excluir_cnpjs'])];
        }

        if (isset($filtros['capital_social_minimo']) || isset($filtros['capital_social_maximo'])) {
            $payload['capital_social'] = [
                'minimo' => isset($filtros['capital_social_minimo']) ? (float) $filtros['capital_social_minimo'] : 0,
                'maximo' => isset($filtros['capital_social_maximo']) ? (float) $filtros['capital_social_maximo'] : 0,
            ];
        }

        $meiOptante = $filtros['mei'] ?? null;
        $meiExclusao = !empty($filtros['mei_excluir']);
        if ($meiOptante !== null || $meiExclusao) {
            $payload['mei'] = [
                'optante' => $meiOptante !== null ? (bool) $meiOptante : true,
                'excluir_optante' => $meiExclusao,
            ];
        }

        $simplesOptante = $filtros['simples'] ?? null;
        $simplesExclusao = !empty($filtros['simples_excluir']);
        if ($simplesOptante !== null || $simplesExclusao) {
            $payload['simples'] = [
                'optante' => $simplesOptante !== null ? (bool) $simplesOptante : true,
                'excluir_optante' => $simplesExclusao,
            ];
        }

        if (!empty($filtros['incluir_atividade_secundaria'])) {
            $payload['incluir_atividade_secundaria'] = true;
        }

        if (!empty($filtros['matriz_filial']) && in_array($filtros['matriz_filial'], ['MATRIZ', 'FILIAL'], true)) {
            $payload['matriz_filial'] = $filtros['matriz_filial'];
        }

        if (!empty($filtros['data_abertura']) && is_array($filtros['data_abertura'])) {
            $dados = $filtros['data_abertura'];
            $dataAbertura = [];
            if (!empty($dados['inicio'])) {
                $dataAbertura['inicio'] = $dados['inicio'];
            }
            if (!empty($dados['fim'])) {
                $dataAbertura['fim'] = $dados['fim'];
            }
            if (!empty($dados['ultimos_dias'])) {
                $dataAbertura['ultimos_dias'] = (int) $dados['ultimos_dias'];
            }
            if (!empty($dataAbertura)) {
                $payload['data_abertura'] = $dataAbertura;
            }
        }

        return $payload;
    }

    private function executarRequisicao(string $metodo, string $url, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Extensao PHP cURL desabilitada. Ative php_curl.dll para acessar a Casa dos Dados.');
        }

        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'api-key: ' . CASA_DOS_DADOS_API_KEY,
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];

        $metodo = strtoupper($metodo);

        if ($metodo === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        } elseif ($metodo === 'GET') {
            $options[CURLOPT_HTTPGET] = true;
        }

        curl_setopt_array($ch, $options);

        $responseBody = curl_exec($ch);
        $httpStatus = $responseBody === false ? 0 : (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = null;
        if ($responseBody && $httpStatus >= 200 && $httpStatus < 300) {
            $decoded = json_decode($responseBody, true);
        }

        $this->registrarLog($url, $payload, $responseBody, $httpStatus);

        return [
            'status' => $httpStatus,
            'raw' => $responseBody,
            'json' => $decoded,
        ];
    }

    /**
     * Normaliza os dados retornados na pesquisa.
     */
    private function montarResumoPesquisa($entrada, array $filtros): array
    {
        $cnpj = null;
        $empresa = 'Empresa nao informada';
        $email = '-';
        $telefone = '-';
        $cidade = $filtros['municipio'] ?? '-';
        $uf = strtoupper($filtros['uf'] ?? '-');

        if (is_string($entrada)) {
            $cnpj = preg_replace('/\D/', '', $entrada);
        } elseif (is_array($entrada)) {
            $cnpj = $this->extrairCampo($entrada, ['cnpj', 'numero_cnpj', 'cnpj_formatado']);
            $empresa = $this->extrairCampo($entrada, ['razao_social', 'nome_fantasia', 'empresa']) ?? $empresa;
            $email = $this->normalizarValorContato($this->extrairCampo($entrada, ['email', 'emails'])) ?? $email;
            $telefone = $this->normalizarValorContato($this->extrairCampo($entrada, ['telefone', 'telefones'])) ?? $telefone;
            $cidade = $this->extrairCampo($entrada, ['municipio', 'cidade']) ?? $cidade;
            $uf = $this->extrairCampo($entrada, ['uf', 'estado', 'sigla_uf']) ?? $uf;
        }

        $cnpjNumerico = $cnpj ? preg_replace('/\D/', '', $cnpj) : null;
        $cnpjFormatado = $cnpjNumerico ? $this->formatarCnpj($cnpjNumerico) : ($cnpj ?: '-');
        $situacaoEntrada = null;
        if (is_array($entrada)) {
            $situacaoCampo = $this->extrairCampo($entrada, ['situacao_cadastral', 'situacao']);
            if (is_array($situacaoCampo) && isset($situacaoCampo['situacao_atual'])) {
                $situacaoEntrada = $situacaoCampo['situacao_atual'];
            } elseif (is_string($situacaoCampo)) {
                $situacaoEntrada = $situacaoCampo;
            }
        }
        if (!$situacaoEntrada && !empty($filtros['situacao_cadastral'])) {
            $situacaoBase = is_array($filtros['situacao_cadastral']) ? $filtros['situacao_cadastral'] : [$filtros['situacao_cadastral']];
            $situacaoEntrada = implode(', ', $situacaoBase);
        }

        return [
            'empresa' => $empresa,
            'segmento' => $filtros['segmento_label'] ?? ($filtros['cnae'] ?? 'Segmento nao informado'),
            'cnpj' => $cnpjNumerico ?: '-',
            'cnpj_formatado' => $cnpjFormatado,
            'cnpj_raw' => $cnpjNumerico,
            'email' => $email,
            'telefone' => $telefone,
            'cidade' => $cidade ?: '-',
            'uf' => $uf ?: '-',
            'situacao' => $situacaoEntrada ? strtoupper($situacaoEntrada) : null,
            '_source' => 'pesquisa',
        ];
    }

    private function extrairCampo(array $dados, array $chaves)
    {
        foreach ($chaves as $chave) {
            if (array_key_exists($chave, $dados)) {
                return $dados[$chave];
            }
        }

        return null;
    }

    private function normalizarValorContato($valor)
    {
        if (empty($valor)) {
            return null;
        }

        if (is_string($valor)) {
            return $valor;
        }

        if (is_array($valor)) {
            $primeiro = reset($valor);
            if (is_array($primeiro)) {
                if (isset($primeiro['email'])) {
                    return $primeiro['email'];
                }
                if (isset($primeiro['numero'])) {
                    return $primeiro['numero'];
                }
                if (isset($primeiro['telefone'])) {
                    return $primeiro['telefone'];
                }
                return reset($primeiro);
            }

            return $primeiro;
        }

        return null;
    }

    private function formatarCnpj(string $cnpj): string
    {
        $numeros = preg_replace('/\D/', '', $cnpj);
        if (strlen($numeros) !== 14) {
            return $cnpj;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($numeros, 0, 2),
            substr($numeros, 2, 3),
            substr($numeros, 5, 3),
            substr($numeros, 8, 4),
            substr($numeros, 12, 2)
        );
    }
}
