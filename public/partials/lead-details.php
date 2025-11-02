<?php
/**
 * public/partials/lead-details.php
 *
 * Helpers para renderizar os dados completos de um lead em um layout amigavel.
 */

if (!function_exists('ld_html')) {
    function ld_html($valor): string
    {
        if ($valor === null || $valor === '') {
            return '-';
        }
        if (is_bool($valor)) {
            return $valor ? 'Sim' : 'Nao';
        }
        if (is_array($valor)) {
            return htmlspecialchars(json_encode($valor, JSON_UNESCAPED_UNICODE));
        }

        return htmlspecialchars((string) $valor);
    }
}

if (!function_exists('ld_format_date')) {
    function ld_format_date(?string $valor): string
    {
        if (empty($valor) || $valor === '0001-01-01T00:00:00Z') {
            return '-';
        }

        try {
            $date = new DateTime($valor);
            return $date->format('d/m/Y');
        } catch (Throwable $e) {
            return htmlspecialchars($valor);
        }
    }
}

if (!function_exists('ld_format_currency')) {
    function ld_format_currency($valor): string
    {
        if ($valor === null || $valor === '') {
            return '-';
        }

        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }
}

if (!function_exists('ld_render_definition_list')) {
    function ld_render_definition_list(array $rows): void
    {
        ?>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
            <?php foreach ($rows as $label => $value): ?>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400"><?php echo htmlspecialchars($label); ?></dt>
                    <dd class="text-sm text-slate-700"><?php echo ld_html($value); ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <?php
    }
}

if (!function_exists('ld_render_chip_list')) {
    function ld_render_chip_list(array $itens, string $emptyMessage = 'Nenhum registro informado'): void
    {
        if (empty($itens)) {
            echo '<p class="text-sm text-slate-500">' . htmlspecialchars($emptyMessage) . '</p>';
            return;
        }

        ?>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($itens as $texto): ?>
                <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                    <?php echo ld_html($texto); ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

if (!function_exists('renderLeadDetails')) {
    function renderLeadDetails(array $dados, array $summary = []): void
    {
        if (empty($dados)) {
            echo '<p class="text-sm text-slate-500">Nenhum detalhe adicional foi retornado pela API.</p>';
            return;
        }

        $cnpjFormatado = $summary['cnpj_formatado']
            ?? ($dados['cnpj_formatado'] ?? null)
            ?? ($summary['cnpj'] ?? $dados['cnpj'] ?? '-');

        $situacao = $dados['situacao_cadastral']['situacao_atual'] ?? ($summary['situacao'] ?? '-');
        $situacaoData = ld_format_date($dados['situacao_cadastral']['data'] ?? null);
        $situacaoMotivo = $dados['situacao_cadastral']['motivo'] ?? '-';

        $emails = array_map(static function ($item) {
            if (is_array($item) && isset($item['email'])) {
                return strtoupper($item['email']);
            }
            return $item;
        }, $dados['contato_email'] ?? []);

        $telefones = array_map(static function ($item) {
            if (is_array($item)) {
                return $item['completo'] ?? (($item['ddd'] ?? '') . ($item['numero'] ?? ''));
            }
            return $item;
        }, $dados['contato_telefonico'] ?? []);

        $atividadePrincipal = $dados['atividade_principal']['codigo'] ?? null
            ? sprintf(
                '%s - %s',
                $dados['atividade_principal']['codigo'],
                $dados['atividade_principal']['descricao'] ?? ''
            )
            : ($summary['segmento'] ?? '-');

        $atividadesSecundarias = array_map(static function ($atividade) {
            if (!is_array($atividade)) {
                return $atividade;
            }

            return sprintf('%s - %s', $atividade['codigo'] ?? '-', $atividade['descricao'] ?? '-');
        }, $dados['atividade_secundaria'] ?? []);

        $socioData = $dados['quadro_societario'] ?? [];

        ?>
        <div class="space-y-6">
            <section class="bg-slate-50 rounded-xl p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-blue-700 mb-4">Dados cadastrais</h2>
                <?php
                ld_render_definition_list([
                    'CNPJ' => $cnpjFormatado,
                    'Razao social' => $dados['razao_social'] ?? ($summary['empresa'] ?? '-'),
                    'Nome fantasia' => $dados['nome_fantasia'] ?? '-',
                    'Situacao' => strtoupper($situacao),
                    'Data da situacao' => $situacaoData,
                    'Motivo da situacao' => $situacaoMotivo,
                    'Porte da empresa' => $dados['porte_empresa']['descricao'] ?? '-',
                    'Natureza juridica' => $dados['descricao_natureza_juridica'] ?? '-',
                    'Capital social' => ld_format_currency($dados['capital_social'] ?? null),
                    'Data de abertura' => ld_format_date($dados['data_abertura'] ?? null),
                    'Data da consulta' => ld_format_date($dados['data_consulta'] ?? null),
                    'Matriz / Filial' => $dados['matriz_filial'] ?? '-',
                    'Qualificacao do responsavel' => $dados['qualificacao_responsavel']['descricao'] ?? '-',
                ]);
                ?>
            </section>

            <section class="bg-slate-50 rounded-xl p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-blue-700 mb-4">Endereco</h2>
                <?php
                $endereco = $dados['endereco'] ?? [];
                ld_render_definition_list([
                    'Logradouro' => $endereco['logradouro'] ?? '-',
                    'Numero' => $endereco['numero'] ?? '-',
                    'Complemento' => $endereco['complemento'] ?? '-',
                    'Bairro' => $endereco['bairro'] ?? '-',
                    'Municipio' => $endereco['municipio'] ?? ($summary['cidade'] ?? '-'),
                    'UF' => $endereco['uf'] ?? ($summary['uf'] ?? '-'),
                    'CEP' => $endereco['cep'] ?? '-',
                    'Latitude' => $endereco['ibge']['latitude'] ?? '-',
                    'Longitude' => $endereco['ibge']['longitude'] ?? '-',
                ]);
                ?>
            </section>

            <section class="bg-slate-50 rounded-xl p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-blue-700 mb-3">Contatos</h2>
                <div class="space-y-3">
                    <div>
                        <h3 class="text-xs uppercase tracking-wide text-slate-400 mb-1">E-mails</h3>
                        <?php
                        if (empty($emails) && !empty($summary['email']) && $summary['email'] !== '-') {
                            $emails[] = $summary['email'];
                        }
                        ld_render_chip_list($emails);
                        ?>
                    </div>
                    <div>
                        <h3 class="text-xs uppercase tracking-wide text-slate-400 mb-1">Telefones</h3>
                        <?php
                        if (empty($telefones) && !empty($summary['telefone']) && $summary['telefone'] !== '-') {
                            $telefones[] = $summary['telefone'];
                        }
                        ld_render_chip_list($telefones);
                        ?>
                    </div>
                </div>
            </section>

            <section class="bg-slate-50 rounded-xl p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-blue-700 mb-3">Atividades</h2>
                <?php
                ld_render_definition_list([
                    'Atividade principal' => $atividadePrincipal,
                ]);
                ?>
                <div class="mt-4">
                    <h3 class="text-xs uppercase tracking-wide text-slate-400 mb-1">Atividades secundarias</h3>
                    <?php
                    if (empty($atividadesSecundarias)) {
                        echo '<p class="text-sm text-slate-500">Nenhuma atividade secundaria informada.</p>';
                    } else {
                        echo '<ul class="list-disc list-inside text-sm text-slate-700 space-y-1">';
                        foreach ($atividadesSecundarias as $atividade) {
                            echo '<li>' . ld_html($atividade) . '</li>';
                        }
                        echo '</ul>';
                    }
                    ?>
                </div>
            </section>

            <section class="bg-slate-50 rounded-xl p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-blue-700 mb-3">Regime tributario</h2>
                <?php
                ld_render_definition_list([
                    'Optante MEI' => $dados['mei']['optante'] ?? null,
                    'CPF MEI' => $dados['mei']['cpf'] ?? '-',
                    'Data opcao MEI' => ld_format_date($dados['mei']['data_opcao_mei'] ?? null),
                    'Data exclusao MEI' => ld_format_date($dados['mei']['data_exclusao_mei'] ?? null),
                    'Optante Simples' => $dados['simples']['optante'] ?? null,
                    'Data opcao Simples' => ld_format_date($dados['simples']['data_opcao_simples'] ?? null),
                    'Data exclusao Simples' => ld_format_date($dados['simples']['data_exclusao_simples'] ?? null),
                ]);
                ?>
            </section>

            <section class="bg-slate-50 rounded-xl p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-blue-700 mb-3">Quadro societario</h2>
                <?php if (empty($socioData)): ?>
                    <p class="text-sm text-slate-500">Nenhum socio informado.</p>
                <?php else: ?>
                    <div class="border border-slate-200 rounded-lg overflow-hidden">
                        <table class="min-w-full text-sm">
                            <thead class="bg-blue-50 text-blue-700">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Nome</th>
                                <th class="px-3 py-2 text-left font-medium">Qualificacao</th>
                                <th class="px-3 py-2 text-left font-medium">Entrada</th>
                                <th class="px-3 py-2 text-left font-medium">Faixa etaria</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            <?php foreach ($socioData as $socio): ?>
                                <tr class="text-slate-700">
                                    <td class="px-3 py-2"><?php echo ld_html($socio['nome'] ?? '-'); ?></td>
                                    <td class="px-3 py-2"><?php echo ld_html($socio['qualificacao_socio'] ?? $socio['qualificacao_representante_legal'] ?? '-'); ?></td>
                                    <td class="px-3 py-2"><?php echo ld_format_date($socio['data_entrada_sociedade'] ?? null); ?></td>
                                    <td class="px-3 py-2"><?php echo ld_html($socio['faixa_etaria_descricao'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }
}
