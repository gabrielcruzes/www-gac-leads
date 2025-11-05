<?php
/**
 * public/buscar-leads.php
 *
 * Pagina para consulta de leads com integracao completa a Casa dos Dados.
 */

use App\Auth;
use App\LeadListService;
use App\LeadService;
use App\SearchHistory;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/LeadService.php';
require_once __DIR__ . '/../src/LeadListService.php';
require_once __DIR__ . '/../src/SearchHistory.php';
require_once __DIR__ . '/components.php';

Auth::requireLogin();

$usuario = Auth::user();
$userId = (int) ($usuario['id'] ?? 0);
$listasUsuario = LeadListService::listarListas($userId);
$pageSize = 100;
$totalResultados = 0;
$hasMoreResults = false;

$ufs = [
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS',
    'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC',
    'SP', 'SE', 'TO',
];

$situacoesDisponiveis = ['ATIVA', 'BAIXADA', 'INAPTA', 'NULA', 'SUSPENSA'];

if (!function_exists('normalizeListField')) {
    function normalizeListField(?string $raw): array
    {
        if ($raw === null) {
            return [];
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[,;\r\n]+/', $raw);
        $parts = array_map(static fn($item) => trim($item), $parts);

        return array_values(array_filter($parts, static fn($item) => $item !== ''));
    }
}

if (!function_exists('normalizeDigitsList')) {
    function normalizeDigitsList(?string $raw): array
    {
        $list = normalizeListField($raw);
        $list = array_map(static fn($item) => preg_replace('/\D+/', '', $item), $list);

        return array_values(array_filter($list, static fn($item) => $item !== ''));
    }
}

if (!function_exists('parseCurrencyValue')) {
    function parseCurrencyValue(?string $raw)
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace(['.', ' '], '', $raw);
        $normalized = str_replace(',', '.', $normalized);

        if (!is_numeric($normalized)) {
            return false;
        }

        return (float) $normalized;
    }
}

if (!function_exists('normalizeMunicipio')) {
    function normalizeMunicipio(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $trimmed);
        if ($converted === false || $converted === null) {
            $converted = $trimmed;
        }

        $sanitized = preg_replace('/[^A-Za-z0-9\s-]/', '', $converted);
        if ($sanitized === null) {
            $sanitized = $converted;
        }

        $condensed = preg_replace('/\s+/', ' ', $sanitized);
        if ($condensed === null) {
            $condensed = $sanitized;
        }

        return strtoupper(trim($condensed));
    }
}

$formState = [
    'cnae' => '',
    'uf' => '',
    'municipio' => '',
    'municipio_display' => '',
    'quantidade' => $pageSize,
    'pagina' => 1,
    'situacao' => 'ATIVA',
    'capital_social_minimo' => '',
    'capital_social_maximo' => '',
    'mei' => '',
    'mei_excluir' => false,
    'simples' => '',
    'simples_excluir' => false,
    'somente_celular' => false,
    'somente_fixo' => false,
    'somente_matriz' => false,
    'somente_filial' => false,
    'com_email' => true,
    'com_telefone' => false,
    'excluir_email_contab' => false,
    'excluir_empresas_visualizadas' => false,
    'codigo_atividade_secundaria' => '',
    'codigo_natureza_juridica' => '',
    'cep' => '',
    'cnpj' => '',
    'matriz_filial' => '',
    'data_abertura_inicio' => '',
    'data_abertura_fim' => '',
    'data_abertura_ultimos_dias' => '',
];

$leads = [];
$errors = [];
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
$historicoBuscas = [];
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['last_export_ready']);
    $cnaeInput = trim($_POST['cnae'] ?? '');
    $formState['cnae'] = preg_replace('/\D/', '', $cnaeInput);
    $ufRecebida = strtoupper(trim($_POST['uf'] ?? ''));
    $formState['uf'] = $ufRecebida === '' ? '' : $ufRecebida;
    $municipioRecebido = trim((string) ($_POST['municipio'] ?? ''));
    $municipioDisplayRecebido = trim((string) ($_POST['municipio_display'] ?? ''));
    $municipioBase = $municipioRecebido !== '' ? $municipioRecebido : $municipioDisplayRecebido;
    $pageNavigation = !empty($_POST['page_navigation']);
    $formState['municipio'] = normalizeMunicipio($municipioBase);
    $formState['municipio_display'] = $municipioDisplayRecebido !== '' ? $municipioDisplayRecebido : $municipioBase;
    if ($formState['municipio_display'] !== '') {
        $formState['municipio_display'] = trim($formState['municipio_display']);
    }
    $formState['quantidade'] = $pageSize;
    $formState['pagina'] = max(1, (int) ($_POST['pagina'] ?? 1));
    if (!$pageNavigation) {
        $formState['pagina'] = 1;
    }
    $formState['capital_social_minimo'] = trim($_POST['capital_social_minimo'] ?? '');
    $formState['capital_social_maximo'] = trim($_POST['capital_social_maximo'] ?? '');
    $formState['mei'] = $_POST['mei'] ?? '';
    $formState['mei_excluir'] = !empty($_POST['mei_excluir']);
    $formState['simples'] = $_POST['simples'] ?? '';
    $formState['simples_excluir'] = !empty($_POST['simples_excluir']);
    $formState['somente_celular'] = !empty($_POST['somente_celular']);
    $formState['somente_fixo'] = !empty($_POST['somente_fixo']);
    $formState['somente_matriz'] = !empty($_POST['somente_matriz']);
    $formState['somente_filial'] = !empty($_POST['somente_filial']);
    $formState['com_email'] = !empty($_POST['com_email']);
    $formState['com_telefone'] = !empty($_POST['com_telefone']);
    $formState['excluir_email_contab'] = !empty($_POST['excluir_email_contab']);
    $formState['excluir_empresas_visualizadas'] = false;
    $formState['codigo_atividade_secundaria'] = trim($_POST['codigo_atividade_secundaria'] ?? '');
    $formState['codigo_natureza_juridica'] = trim($_POST['codigo_natureza_juridica'] ?? '');
    $formState['cep'] = trim($_POST['cep'] ?? '');
    $formState['cnpj'] = trim($_POST['cnpj'] ?? '');
    $formState['matriz_filial'] = strtoupper(trim($_POST['matriz_filial'] ?? ''));
    $formState['data_abertura_inicio'] = trim($_POST['data_abertura_inicio'] ?? '');
    $formState['data_abertura_fim'] = trim($_POST['data_abertura_fim'] ?? '');
    $formState['data_abertura_ultimos_dias'] = trim($_POST['data_abertura_ultimos_dias'] ?? '');

    $situacaoPost = strtoupper(trim((string) ($_POST['situacao'] ?? 'ATIVA')));
    if (!in_array($situacaoPost, $situacoesDisponiveis, true)) {
        $situacaoPost = 'ATIVA';
    }
    $formState['situacao'] = $situacaoPost;

    if ($formState['cnae'] !== '' && !preg_match('/^\d{7}$/', $formState['cnae'])) {
        $errors[] = 'O CNAE deve conter exatamente 7 digitos numericos.';
    }

    if ($formState['uf'] !== '' && !in_array($formState['uf'], $ufs, true)) {
        $errors[] = 'Selecione uma UF valida.';
    }

    $capitalMin = parseCurrencyValue($formState['capital_social_minimo']);
    if ($capitalMin === false) {
        $errors[] = 'Capital social minimo invalido.';
    } elseif ($capitalMin !== null && $capitalMin < 0) {
        $errors[] = 'Capital social minimo nao pode ser negativo.';
    }

    $capitalMax = parseCurrencyValue($formState['capital_social_maximo']);
    if ($capitalMax === false) {
        $errors[] = 'Capital social maximo invalido.';
    } elseif ($capitalMax !== null && $capitalMax < 0) {
        $errors[] = 'Capital social maximo nao pode ser negativo.';
    }

    if ($capitalMin !== null && $capitalMax !== null && $capitalMin > $capitalMax) {
        $errors[] = 'O capital social minimo nao pode ser maior que o maximo.';
    }

    if ($formState['data_abertura_inicio'] !== '' && !DateTime::createFromFormat('Y-m-d', $formState['data_abertura_inicio'])) {
        $errors[] = 'Data de abertura (inicio) invalida.';
    }
    if ($formState['data_abertura_fim'] !== '' && !DateTime::createFromFormat('Y-m-d', $formState['data_abertura_fim'])) {
        $errors[] = 'Data de abertura (fim) invalida.';
    }
    if ($formState['data_abertura_ultimos_dias'] !== '' && (!ctype_digit($formState['data_abertura_ultimos_dias']) || (int) $formState['data_abertura_ultimos_dias'] <= 0)) {
        $errors[] = 'Informe um numero valido em "ultimos dias".';
    }

    if (empty($errors)) {
        $filtros = [
            'segmento_label' => $formState['cnae'] !== '' ? 'CNAE ' . $formState['cnae'] : 'Consulta personalizada',
            'municipio' => $formState['municipio'],
            'municipio_display' => $formState['municipio_display'],
            'quantidade' => $pageSize,
            'pagina' => $formState['pagina'],
            'situacao_cadastral' => $formState['situacao'],
            'somente_celular' => $formState['somente_celular'],
            'somente_fixo' => $formState['somente_fixo'],
            'somente_matriz' => $formState['somente_matriz'],
            'somente_filial' => $formState['somente_filial'],
            'com_email' => $formState['com_email'],
            'com_telefone' => $formState['com_telefone'],
            'excluir_email_contab' => $formState['excluir_email_contab'],
            'excluir_empresas_visualizadas' => false,
            'matriz_filial' => $formState['matriz_filial'],
        ];

        if ($formState['cnae'] !== '') {
            $filtros['cnae'] = $formState['cnae'];
        }

        if ($formState['uf'] !== '') {
            $filtros['uf'] = $formState['uf'];
        }

        if ($capitalMin !== null) {
            $filtros['capital_social_minimo'] = $capitalMin;
        }
        if ($capitalMax !== null) {
            $filtros['capital_social_maximo'] = $capitalMax;
        }

        $codigoSecundario = normalizeListField($formState['codigo_atividade_secundaria']);
        if ($codigoSecundario) {
            $filtros['codigo_atividade_secundaria'] = $codigoSecundario;
        }

        $codigoNatureza = normalizeDigitsList($formState['codigo_natureza_juridica']);
        if ($codigoNatureza) {
            $filtros['codigo_natureza_juridica'] = $codigoNatureza;
        }

        $cepFiltro = normalizeDigitsList($formState['cep']);
        if ($cepFiltro) {
            $filtros['cep'] = $cepFiltro;
        }

        $cnpjFiltro = normalizeDigitsList($formState['cnpj']);
        if ($cnpjFiltro) {
            $filtros['cnpj'] = $cnpjFiltro;
        }

        if ($formState['mei'] === 'optante') {
            $filtros['mei'] = true;
        } elseif ($formState['mei'] === 'nao_optante') {
            $filtros['mei'] = false;
        } else {
            $filtros['mei'] = null;
        }
        $filtros['mei_excluir'] = $formState['mei_excluir'];

        if ($formState['simples'] === 'optante') {
            $filtros['simples'] = true;
        } elseif ($formState['simples'] === 'nao_optante') {
            $filtros['simples'] = false;
        } else {
            $filtros['simples'] = null;
        }
        $filtros['simples_excluir'] = $formState['simples_excluir'];

        if (!empty($formState['data_abertura_inicio']) || !empty($formState['data_abertura_fim']) || !empty($formState['data_abertura_ultimos_dias'])) {
            $filtros['data_abertura'] = [
                'inicio' => $formState['data_abertura_inicio'] ?: null,
                'fim' => $formState['data_abertura_fim'] ?: null,
                'ultimos_dias' => $formState['data_abertura_ultimos_dias'] !== '' ? (int) $formState['data_abertura_ultimos_dias'] : null,
            ];
        }


        try {
            $resultadoBusca = LeadService::buscarLeads($filtros);
            $leads = $resultadoBusca['leads'] ?? [];
            $totalResultados = (int) ($resultadoBusca['total'] ?? count($leads));
            $hasMoreResults = (bool) ($resultadoBusca['has_more'] ?? false);

            try {
                SearchHistory::registrar($userId, $formState, $totalResultados);
            } catch (\Throwable $historyException) {
                error_log('Erro ao salvar historico de buscas: ' . $historyException->getMessage());
            }

            $_SESSION['last_search_total'] = $totalResultados;
            $_SESSION['last_search_export'] = [
                'segmento' => $filtros['segmento_label'],
                'leads' => $leads,
                'filters' => $formState,
                'total' => $totalResultados,
            ];
            $_SESSION['last_search_token'] = bin2hex(random_bytes(8));
            unset($_SESSION['last_export_ready']);
        } catch (Throwable $exception) {
            error_log('Erro ao buscar leads: ' . $exception->getMessage());
            if (stripos($exception->getMessage(), 'cURL') !== false) {
                $errors[] = 'Ative a extensao PHP cURL (php_curl.dll) para consultar a API da Casa dos Dados.';
            } else {
                $errors[] = 'Nao foi possivel consultar a API no momento. Detalhes: ' . $exception->getMessage();
            }
            $leads = [];
            $totalResultados = 0;
            $hasMoreResults = false;
            $_SESSION['last_search_total'] = 0;
            unset($_SESSION['last_search_export'], $_SESSION['last_search_token'], $_SESSION['last_export_ready']);
        }
}
}

$historicoBuscas = SearchHistory::listar($userId, 5);

renderPageStart('Buscar Leads', 'buscar');
?>
    <div class="bg-white rounded-xl shadow p-6 mb-8">
        <h1 class="text-xl font-semibold text-blue-700 mb-4">Buscar leads B2B</h1>
        <form id="lead-search-form" method="post" class="grid md:grid-cols-6 gap-4 items-start">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-600 mb-1">Segmento (CNAE)</label>
                <input type="text" name="cnae" id="search-cnae" pattern="[0-9]*" maxlength="7" inputmode="numeric"
                       placeholder="Opcional - ex.: 6201501"
                       value="<?php echo htmlspecialchars($formState['cnae']); ?>"
                       class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                <p class="text-xs text-slate-400 mt-1">Informe os 7 digitos do CNAE somente se desejar filtrar pelo segmento.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">UF</label>
                <select name="uf" id="search-uf" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                    <option value="">Todos os estados</option>
                    <?php foreach ($ufs as $uf): ?>
                        <option value="<?php echo $uf; ?>" <?php echo $formState['uf'] === $uf ? 'selected' : ''; ?>>
                            <?php echo $uf; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Municipio</label>
                <select name="municipio" id="search-municipio" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" data-initial-municipio="<?php echo htmlspecialchars($formState['municipio']); ?>" data-initial-display="<?php echo htmlspecialchars($formState['municipio_display']); ?>" <?php echo $formState['uf'] === '' ? 'disabled' : ''; ?>>
                    <option value="">Todos os municipios</option>
                </select>
                <input type="hidden" name="municipio_display" id="search-municipio-display" value="<?php echo htmlspecialchars($formState['municipio_display']); ?>">

                <noscript>
                    <p class="text-xs text-red-500 mt-1">Ative o JavaScript para selecionar municipios.</p>
                </noscript>
            </div>
            <input type="hidden" name="pagina" id="search-pagina" value="<?php echo (int) $formState['pagina']; ?>">
            <input type="hidden" name="page_navigation" id="search-page-navigation" value="">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-600 mb-1">Situacao cadastral</label>
                <select name="situacao" id="search-situacao" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                    <?php foreach ($situacoesDisponiveis as $situacao): ?>
                        <option value="<?php echo $situacao; ?>" <?php echo $formState['situacao'] === $situacao ? 'selected' : ''; ?>>
                            <?php echo $situacao; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Matriz / Filial</label>
                <select name="matriz_filial" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                    <option value="">Qualquer</option>
                    <option value="MATRIZ" <?php echo $formState['matriz_filial'] === 'MATRIZ' ? 'selected' : ''; ?>>Somente matriz</option>
                    <option value="FILIAL" <?php echo $formState['matriz_filial'] === 'FILIAL' ? 'selected' : ''; ?>>Somente filial</option>
                </select>
            </div>
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-slate-600 mb-1">CNPJs</label>
                <textarea name="cnpj" rows="3" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Separe multiplos CNPJs com virgula ou quebra de linha"><?php echo htmlspecialchars($formState['cnpj']); ?></textarea>
            </div>

            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-slate-600 mb-1">Codigos CNAE secundarios</label>
                <textarea name="codigo_atividade_secundaria" rows="3" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Separe com virgula ou quebra de linha"><?php echo htmlspecialchars($formState['codigo_atividade_secundaria']); ?></textarea>
            </div>
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-slate-600 mb-1">Codigos de natureza juridica</label>
                <textarea name="codigo_natureza_juridica" rows="3" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Separe com virgula ou quebra de linha"><?php echo htmlspecialchars($formState['codigo_natureza_juridica']); ?></textarea>
            </div>

            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-slate-600 mb-1">CEP</label>
                <textarea name="cep" rows="3" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Ex.: 01001000"><?php echo htmlspecialchars($formState['cep']); ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Capital social minimo (R$)</label>
                <input type="text" name="capital_social_minimo" value="<?php echo htmlspecialchars($formState['capital_social_minimo']); ?>" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Opcional">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Capital social maximo (R$)</label>
                <input type="text" name="capital_social_maximo" value="<?php echo htmlspecialchars($formState['capital_social_maximo']); ?>" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Opcional">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Data de abertura (inicio)</label>
                <input type="date" name="data_abertura_inicio" value="<?php echo htmlspecialchars($formState['data_abertura_inicio']); ?>" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Data de abertura (fim)</label>
                <input type="date" name="data_abertura_fim" value="<?php echo htmlspecialchars($formState['data_abertura_fim']); ?>" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Ultimos dias</label>
                <input type="number" min="1" name="data_abertura_ultimos_dias" value="<?php echo htmlspecialchars($formState['data_abertura_ultimos_dias']); ?>" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Ex.: 30">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Optante MEI</label>
                <select name="mei" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                    <option value="">Qualquer</option>
                    <option value="optante" <?php echo $formState['mei'] === 'optante' ? 'selected' : ''; ?>>Somente optantes</option>
                    <option value="nao_optante" <?php echo $formState['mei'] === 'nao_optante' ? 'selected' : ''; ?>>Somente nao optantes</option>
                </select>
                <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="mei_excluir" value="1" <?php echo $formState['mei_excluir'] ? 'checked' : ''; ?>>
                    <span>Excluir optantes</span>
                </label>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Optante Simples</label>
                <select name="simples" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                    <option value="">Qualquer</option>
                    <option value="optante" <?php echo $formState['simples'] === 'optante' ? 'selected' : ''; ?>>Somente optantes</option>
                    <option value="nao_optante" <?php echo $formState['simples'] === 'nao_optante' ? 'selected' : ''; ?>>Somente nao optantes</option>
                </select>
                <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="simples_excluir" value="1" <?php echo $formState['simples_excluir'] ? 'checked' : ''; ?>>
                    <span>Excluir optantes</span>
                </label>
            </div>

            <div class="md:col-span-3">
                <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Contatos</p>
                <label class="flex items-center gap-2 text-sm text-slate-600 mb-1">
                    <input type="checkbox" name="com_email" value="1" <?php echo $formState['com_email'] ? 'checked' : ''; ?>>
                    <span>Somente empresas com e-mail</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600 mb-1">
                    <input type="checkbox" name="com_telefone" value="1" <?php echo $formState['com_telefone'] ? 'checked' : ''; ?>>
                    <span>Somente empresas com telefone</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600 mb-1">
                    <input type="checkbox" name="somente_celular" value="1" <?php echo $formState['somente_celular'] ? 'checked' : ''; ?>>
                    <span>Somente contatos com celular</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600 mb-1">
                    <input type="checkbox" name="somente_fixo" value="1" <?php echo $formState['somente_fixo'] ? 'checked' : ''; ?>>
                    <span>Somente telefones fixos</span>
                </label>
            </div>

            <div class="md:col-span-3">
                <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Outros filtros</p>
                <label class="flex items-center gap-2 text-sm text-slate-600 mb-1">
                    <input type="checkbox" name="somente_matriz" value="1" <?php echo $formState['somente_matriz'] ? 'checked' : ''; ?>>
                    <span>Somente matriz</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600 mb-1">
                    <input type="checkbox" name="somente_filial" value="1" <?php echo $formState['somente_filial'] ? 'checked' : ''; ?>>
                    <span>Somente filial</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600 mb-1">
                    <input type="checkbox" name="excluir_email_contab" value="1" <?php echo $formState['excluir_email_contab'] ? 'checked' : ''; ?>>
                    <span>Excluir e-mails contabeis</span>
                </label>
            </div>

            <div class="md:col-span-6 flex justify-end">
                <button type="submit" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg">
                    Buscar
                </button>
            </div>
        </form>

        <?php if ($flashSuccess): ?>
            <div class="rounded-lg bg-green-100 text-green-700 px-4 py-3 mt-4 text-sm">
                <?php echo htmlspecialchars($flashSuccess); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($historicoBuscas)): ?>
            <div class="bg-white rounded-xl shadow p-6 mb-8">
                <h2 class="text-lg font-semibold text-blue-700 mb-3">Historico de pesquisas</h2>
                <ul class="space-y-3">
                    <?php foreach ($historicoBuscas as $registro): ?>
                        <?php $filtroRegistro = is_array($registro['filters'] ?? null) ? $registro['filters'] : []; ?>
                        <?php
                            $historicoCnae = $filtroRegistro['cnae'] ?? '';
                            $historicoUf = $filtroRegistro['uf'] ?? '';
                            if (is_array($historicoUf)) {
                                $historicoUf = implode(', ', $historicoUf);
                            }
                            $historicoMunicipio = $filtroRegistro['municipio'] ?? '';
                            $historicoMunicipioDisplay = $filtroRegistro['municipio_display'] ?? '';
                            if ($historicoMunicipioDisplay === '' && $historicoMunicipio !== '') {
                                $historicoMunicipioDisplay = $historicoMunicipio;
                            }
                            $historicoSituacao = $filtroRegistro['situacao'] ?? ($filtroRegistro['situacao_cadastral'] ?? 'ATIVA');
                            if (is_array($historicoSituacao)) {
                                $historicoSituacao = $historicoSituacao[0] ?? 'ATIVA';
                            }
                            $dadosReaplicar = $filtroRegistro;
                            if (!isset($dadosReaplicar['situacao']) && isset($dadosReaplicar['situacao_cadastral'])) {
                                $dadosReaplicar['situacao'] = $dadosReaplicar['situacao_cadastral'];
                            }
                            $dadosReaplicar['cnae'] = $historicoCnae;
                            $dadosReaplicar['uf'] = $filtroRegistro['uf'] ?? '';
                            $dadosReaplicar['municipio'] = $historicoMunicipio;
                            $dadosReaplicar['municipio_display'] = $historicoMunicipioDisplay;
                            $dadosReaplicar['pagina'] = 1;
                            $dadosReaplicar['situacao'] = $historicoSituacao;
                            $dadosReaplicarAttr = htmlspecialchars(json_encode($dadosReaplicar, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        ?>
                        <li class="border border-slate-200 rounded-lg px-4 py-3 bg-slate-50">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium text-blue-700">
                                        <?php echo $historicoCnae !== '' ? 'CNAE ' . htmlspecialchars($historicoCnae) : 'Consulta personalizada'; ?>
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        UF: <?php echo htmlspecialchars($historicoUf !== '' ? $historicoUf : '-'); ?> |
                                        Municipio: <?php echo htmlspecialchars($historicoMunicipioDisplay !== '' ? $historicoMunicipioDisplay : '-'); ?> |
                                        Resultados: <?php echo (int) ($registro['results_count'] ?? 0); ?> |
                                        Pagina: <?php echo (int) ($filtroRegistro['pagina'] ?? 1); ?>
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-slate-500">
                                    <span><?php echo date('d/m/Y H:i', strtotime($registro['created_at'] ?? 'now')); ?></span>
                                    <button type="button" class="history-apply text-blue-600 hover:text-blue-700 font-medium" data-filtros="<?php echo $dadosReaplicarAttr; ?>">
                                        Reaplicar
                                    </button>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="rounded-lg bg-red-100 text-red-700 px-4 py-3 mt-4 text-sm">
                <?php echo htmlspecialchars($flashError); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="rounded-lg bg-red-100 text-red-700 px-4 py-3 mt-4 space-y-1 text-sm">
                <?php foreach ($errors as $erro): ?>
                    <p><?php echo htmlspecialchars($erro); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php if (!empty($leads)): ?>
    <?php
        $currentPage = max(1, (int) $formState['pagina']);
        $hasPreviousPage = $currentPage > 1;
        $totalPaginas = $totalResultados > 0 ? (int) max(1, ceil($totalResultados / $pageSize)) : ($currentPage + ($hasMoreResults ? 1 : 0));
        $hasNextPage = $hasMoreResults || ($totalResultados > ($currentPage * $pageSize));
    ?>
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-blue-700">Resultados</h2>
                <p class="text-xs text-slate-500 mt-1">
                    Exibindo <?php echo count($leads); ?> empresas na pagina <?php echo $currentPage; ?> de <?php echo max(1, $totalPaginas); ?> (<?php echo $pageSize; ?> por pagina). Total encontrados: <?php echo (int) $totalResultados; ?>.
                </p>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex items-center gap-2">
                    <button type="button" class="pagination-button bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium px-3 py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed" data-page-shift="-1" <?php echo $hasPreviousPage ? '' : 'disabled'; ?>>
                        Pagina anterior
                    </button>
                    <span class="text-sm text-slate-500">Pagina <?php echo $currentPage; ?></span>
                    <button type="button" class="pagination-button bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium px-3 py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed" data-page-shift="1" <?php echo $hasNextPage ? '' : 'disabled'; ?>>
                        Proxima pagina
                    </button>
                </div>
                <form method="post" action="exportar-csv.php" class="flex items-center gap-3">
                    <input type="hidden" name="export_token" value="<?php echo htmlspecialchars($_SESSION['last_search_token'] ?? ''); ?>">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg">
                        Exportar CSV
                    </button>
                </form>
            </div>
        </div>

        <?php if (!empty($listasUsuario)): ?>
            <form id="bulk-add-form" method="post" action="adicionar-lead-lista.php" class="mb-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4 border border-slate-200 rounded-lg px-4 py-3 bg-slate-50">
                <input type="hidden" name="redirect" value="buscar-leads.php">
                <div class="flex flex-col md:flex-row md:items-center gap-3 w-full md:w-auto">
                    <label class="text-sm text-slate-600 flex items-center gap-2">
                        Lista
                        <select name="lista_id" id="bulk-lista-select" class="border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                            <option value="">Selecione uma lista</option>
                            <?php foreach ($listasUsuario as $lista): ?>
                                <option value="<?php echo (int) $lista['id']; ?>"><?php echo htmlspecialchars($lista['name']); ?> (<?php echo (int) ($lista['total_items'] ?? 0); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <span class="text-sm text-slate-500">Selecionados: <span id="bulk-count-display">0</span></span>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" id="bulk-submit-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg opacity-50 cursor-not-allowed" disabled>
                        Adicionar sele&ccedil;&atilde;o
                    </button>
                </div>
                <div id="bulk-hidden-container" style="display:none;"></div>
            </form>
        <?php endif; ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                <tr class="bg-blue-50 text-blue-700 text-left">
                    <th class="px-4 py-2 font-medium w-10">
                        <input type="checkbox" id="bulk-select-all" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    </th>
                    <th class="px-4 py-2 font-medium">Empresa</th>
                    <th class="px-4 py-2 font-medium">Segmento</th>
                    <th class="px-4 py-2 font-medium">CNPJ</th>
                    <th class="px-4 py-2 font-medium">E-mail</th>
                    <th class="px-4 py-2 font-medium">Telefone</th>
                    <th class="px-4 py-2 font-medium">Municipio</th>
                    <th class="px-4 py-2 font-medium">UF</th>
                    <th class="px-4 py-2 font-medium">Situacao</th>
                    <th class="px-4 py-2 font-medium">Acoes</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($leads as $lead): ?>
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-2">
                            <input type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 bulk-lead-checkbox" value="<?php echo htmlspecialchars($lead['token']); ?>">
                        </td>
                        <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['empresa'] ?? '-'); ?></td>
                        <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['segmento'] ?? '-'); ?></td>
                        <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['cnpj_formatado'] ?? ($lead['cnpj'] ?? '-')); ?></td>
                        <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['email'] ?? '-'); ?></td>
                        <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['telefone'] ?? '-'); ?></td>
                        <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['cidade'] ?? '-'); ?></td>
                        <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['uf'] ?? '-'); ?></td>
                        <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['situacao'] ?? '-'); ?></td>
                        <td class="px-4 py-2 space-y-3">
                            <div>
                                <a href="lead-detalhe.php?id=<?php echo urlencode($lead['token']); ?>" class="text-blue-600 hover:text-blue-700 font-medium lead-view-link">
                                    Ver detalhes
                                </a>
                            </div>
                            <div>
                                <?php if (!empty($listasUsuario)): ?>
                                    <form method="post" action="adicionar-lead-lista.php" class="space-y-2 single-add-form">
                                        <input type="hidden" name="lead_token" value="<?php echo htmlspecialchars($lead['token']); ?>">
                                        <input type="hidden" name="redirect" value="buscar-leads.php">
                                        <label class="block text-xs font-medium text-slate-500">Adicionar em</label>
                                        <select name="lista_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-blue-600">
                                            <option value="">Selecione uma lista</option>
                                            <?php foreach ($listasUsuario as $lista): ?>
                                                <option value="<?php echo (int) $lista['id']; ?>"><?php echo htmlspecialchars($lista['name']); ?> (<?php echo (int) ($lista['total_items'] ?? 0); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium px-3 py-2 rounded-lg">
                                            Enviar para lista
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <p class="text-xs text-slate-500">
                                        Crie sua primeira <a href="listas.php" class="text-blue-600 hover:text-blue-700 font-medium lead-view-link">lista de leads</a> para organizar resultados.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)): ?>
    <div class="bg-white rounded-xl shadow p-6">
        <p class="text-sm text-slate-500">Nenhum lead encontrado para os filtros informados.</p>
    </div>
<?php endif; ?>
<script src="assets/js/buscar-leads.js"></script>
<?php
renderPageEnd();















