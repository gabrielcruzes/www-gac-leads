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
    'ncm' => '',
    'uf' => '',
    'municipio' => '',
    'municipio_display' => '',
    'quantidade' => 100,
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
    'excluir_empresas_visualizadas' => true,
    'codigo_atividade_secundaria' => '',
    'codigo_natureza_juridica' => '',
    'cep' => '',
    'cnpj' => '',
    'ddd' => '',
    'incluir_atividade_secundaria' => false,
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
    $cnaeInput = trim($_POST['cnae'] ?? '');
    $formState['cnae'] = preg_replace('/\D/', '', $cnaeInput);
    $formState['ncm'] = trim($_POST['ncm'] ?? '');
    $ufRecebida = strtoupper(trim($_POST['uf'] ?? ''));
    $formState['uf'] = $ufRecebida === '' ? '' : $ufRecebida;
    $municipioRecebido = trim((string) ($_POST['municipio'] ?? ''));
    $municipioDisplayRecebido = trim((string) ($_POST['municipio_display'] ?? ''));
    $municipioBase = $municipioRecebido !== '' ? $municipioRecebido : $municipioDisplayRecebido;
    $formState['municipio'] = normalizeMunicipio($municipioBase);
    $formState['municipio_display'] = $municipioDisplayRecebido !== '' ? $municipioDisplayRecebido : $municipioBase;
    if ($formState['municipio_display'] !== '') {
        $formState['municipio_display'] = trim($formState['municipio_display']);
    }
    $formState['quantidade'] = max(0, (int) ($_POST['quantidade'] ?? 0));
    $formState['pagina'] = max(1, (int) ($_POST['pagina'] ?? 1));
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
    $formState['excluir_empresas_visualizadas'] = !empty($_POST['excluir_empresas_visualizadas']);
    $formState['codigo_atividade_secundaria'] = trim($_POST['codigo_atividade_secundaria'] ?? '');
    $formState['codigo_natureza_juridica'] = trim($_POST['codigo_natureza_juridica'] ?? '');
    $formState['cep'] = trim($_POST['cep'] ?? '');
    $formState['cnpj'] = trim($_POST['cnpj'] ?? '');
    $formState['ddd'] = trim($_POST['ddd'] ?? '');
    $formState['incluir_atividade_secundaria'] = !empty($_POST['incluir_atividade_secundaria']);
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

    if ($formState['quantidade'] <= 0) {
        $errors[] = 'Informe um limite de resultados valido.';
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
            'quantidade' => $formState['quantidade'],
            'pagina' => $formState['pagina'],
            'situacao_cadastral' => $formState['situacao'],
            'somente_celular' => $formState['somente_celular'],
            'somente_fixo' => $formState['somente_fixo'],
            'somente_matriz' => $formState['somente_matriz'],
            'somente_filial' => $formState['somente_filial'],
            'com_email' => $formState['com_email'],
            'com_telefone' => $formState['com_telefone'],
            'excluir_email_contab' => $formState['excluir_email_contab'],
            'excluir_empresas_visualizadas' => $formState['excluir_empresas_visualizadas'],
            'incluir_atividade_secundaria' => $formState['incluir_atividade_secundaria'],
            'matriz_filial' => $formState['matriz_filial'],
        ];

        if ($formState['cnae'] !== '') {
            $filtros['cnae'] = $formState['cnae'];
        }

        $ncmFiltro = normalizeListField($formState['ncm']);
        if ($ncmFiltro) {
            $ncmSanitized = array_map(static fn($item) => preg_replace('/\D+/', '', $item), $ncmFiltro);
            $ncmSanitized = array_values(array_filter($ncmSanitized, static fn($item) => $item !== ''));
            if ($ncmSanitized) {
                $filtros['ncm'] = $ncmSanitized;
            }
        }
        if ($formState['ncm'] !== '') {
            $filtros['ncm_display'] = $formState['ncm'];
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

        $dddFiltro = normalizeDigitsList($formState['ddd']);
        if ($dddFiltro) {
            $filtros['ddd'] = $dddFiltro;
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
            $leads = LeadService::buscarLeads($filtros);

            try {
                SearchHistory::registrar($userId, $formState, count($leads));
            } catch (\Throwable $historyException) {
                error_log('Erro ao salvar historico de buscas: ' . $historyException->getMessage());
            }

            $_SESSION['last_search_export'] = [
                'segmento' => $filtros['segmento_label'],
                'leads' => $leads,
                'filters' => $formState,
            ];
            $_SESSION['last_search_token'] = bin2hex(random_bytes(8));
        } catch (Throwable $exception) {
            error_log('Erro ao buscar leads: ' . $exception->getMessage());
            if (stripos($exception->getMessage(), 'cURL') !== false) {
                $errors[] = 'Ative a extensao PHP cURL (php_curl.dll) para consultar a API da Casa dos Dados.';
            } else {
                $errors[] = 'Nao foi possivel consultar a API no momento. Detalhes: ' . $exception->getMessage();
            }
        }
}
}

$historicoBuscas = SearchHistory::listar($userId, 10);

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
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-600 mb-1">NCM</label>
                <input type="text" name="ncm" id="search-ncm" value="<?php echo htmlspecialchars($formState['ncm']); ?>" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Opcional - ex.: 3305.10.00">
                <p class="text-xs text-slate-400 mt-1">Adicione um ou mais codigos NCM separados por virgula. Use a consulta de NCM abaixo para buscar pela descricao.</p>
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
                <p class="text-xs text-slate-400 mt-1">Escolha uma UF para carregar as cidades disponiveis.</p>
                <noscript>
                    <p class="text-xs text-red-500 mt-1">Ative o JavaScript para selecionar municipios.</p>
                </noscript>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Limite de resultados</label>
                <input type="number" min="1" max="100" name="quantidade" id="search-quantidade" value="<?php echo (int) $formState['quantidade']; ?>" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">           </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Pagina</label>
                <input type="number" min="1" name="pagina" value="<?php echo (int) $formState['pagina']; ?>" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
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
                <label class="block text-sm font-medium text-slate-600 mb-1">DDD</label>
                <input type="text" name="ddd" value="<?php echo htmlspecialchars($formState['ddd']); ?>" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Ex.: 11, 21">
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
                    <input type="checkbox" name="excluir_empresas_visualizadas" value="1" <?php echo $formState['excluir_empresas_visualizadas'] ? 'checked' : ''; ?>>
                    <span>Excluir empresas ja visualizadas</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600 mb-1">
                    <input type="checkbox" name="excluir_email_contab" value="1" <?php echo $formState['excluir_email_contab'] ? 'checked' : ''; ?>>
                    <span>Excluir e-mails contabeis</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600 mb-1">
                    <input type="checkbox" name="incluir_atividade_secundaria" value="1" <?php echo $formState['incluir_atividade_secundaria'] ? 'checked' : ''; ?>>
                    <span>Incluir CNAEs secundarios na busca</span>
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
                            $historicoNcm = $filtroRegistro['ncm_display'] ?? '';
                            if ($historicoNcm === '' && !empty($filtroRegistro['ncm'])) {
                                $historicoNcm = is_array($filtroRegistro['ncm']) ? implode(', ', $filtroRegistro['ncm']) : (string) $filtroRegistro['ncm'];
                            }
                            $historicoQuantidade = $filtroRegistro['quantidade'] ?? '';
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
                            $dadosReaplicar['ncm_display'] = $historicoNcm;
                            $dadosReaplicar['ncm'] = $historicoNcm;
                            $dadosReaplicar['quantidade'] = $historicoQuantidade;
                            $dadosReaplicar['pagina'] = $filtroRegistro['pagina'] ?? 1;
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
                                        NCM: <?php echo htmlspecialchars($historicoNcm !== '' ? $historicoNcm : '-'); ?> |
                                        Resultados: <?php echo (int) ($registro['results_count'] ?? 0); ?>
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

        <div class="bg-white rounded-xl shadow p-6 mt-8" id="ncm-helper-card">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-blue-700">Consulta NCM</h2>
                    <p class="text-sm text-slate-500">Pesquise codigos NCM pela descricao ou codigo para apoiar sua prospeccao.</p>
                </div>
                <form id="ncm-search-form" class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                    <input type="search" id="ncm-query" name="q" class="w-full md:w-72 border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Ex.: cosmeticos, xampus">
                    <button type="submit" class="w-full md:w-auto bg-slate-800 hover:bg-slate-900 text-white font-medium px-4 py-2 rounded-lg">Buscar NCM</button>
                </form>
            </div>
            <p id="ncm-status" class="text-xs text-slate-500 mb-3">Digite um termo para pesquisar. Resultados limitados aos 25 primeiros itens.</p>
            <div id="ncm-results" class="overflow-x-auto">
                <p class="text-sm text-slate-500">Nenhuma pesquisa realizada ainda.</p>
            </div>
        </div>
    </div>

<?php if (!empty($leads)): ?>
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-blue-700">Resultados</h2>
                <p class="text-xs text-slate-500 mt-1">
                    Exibindo <?php echo count($leads); ?> empresas para o filtro selecionado.
                </p>
            </div>
            <form method="post" action="exportar-csv.php" class="flex items-center gap-3">
                <input type="hidden" name="export_token" value="<?php echo htmlspecialchars($_SESSION['last_search_token'] ?? ''); ?>">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg">
                    Exportar CSV
                </button>
            </form>
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchForm = document.getElementById('lead-search-form');
            const cnaeInput = document.getElementById('search-cnae');
            const ncmFilterField = document.getElementById('search-ncm');
            const ufSelect = document.getElementById('search-uf');
            const municipioSelect = document.getElementById('search-municipio');
            const municipioDisplayInput = document.getElementById('search-municipio-display');
            const defaultMunicipioOptionLabel = 'Todos os municipios';
            let municipioRequestId = 0;
            let lastMunicipioUf = '';
            const escapeHtml = function (value) {
                if (value === undefined || value === null) {
                    return '';
                }
                return String(value).replace(/[&<>"']/g, function (character) {
                    switch (character) {
                        case '&':
                            return '&amp;';
                        case '<':
                            return '&lt;';
                        case '>':
                            return '&gt;';
                        case '"':
                            return '&quot;';
                        case '\'':
                            return '&#39;';
                        default:
                            return character;
                    }
                });
            };

            const normalizeMunicipioValue = function (value) {
                if (!value) {
                    return '';
                }

                let result = String(value);
                if (typeof result.normalize === 'function') {
                    result = result.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                }
                result = result.replace(/[^A-Za-z0-9\s-]/g, '');
                result = result.replace(/\s+/g, ' ');

                return result.trim().toUpperCase();
            };

            const resetMunicipioSelect = function (shouldDisable) {
                if (!municipioSelect) {
                    return;
                }

                municipioSelect.innerHTML = '';
                const option = document.createElement('option');
                option.value = '';
                option.textContent = defaultMunicipioOptionLabel;
                municipioSelect.appendChild(option);
                municipioSelect.disabled = Boolean(shouldDisable);
                municipioSelect.classList.remove('opacity-60');
                municipioSelect.dataset.loadedUf = '';
                lastMunicipioUf = '';

                if (municipioDisplayInput) {
                    municipioDisplayInput.value = '';
                }
            };

            const syncMunicipioDisplay = function () {
                if (!municipioSelect || !municipioDisplayInput) {
                    return;
                }

                const option = municipioSelect.options[municipioSelect.selectedIndex];
                if (option && option.value !== '') {
                    municipioDisplayInput.value = option.dataset.display || option.textContent || '';
                } else {
                    municipioDisplayInput.value = '';
                }
            };

            const populateMunicipios = function (lista, selectedValueNormalized, selectedDisplay) {
                if (!municipioSelect) {
                    return;
                }

                const fragment = document.createDocumentFragment();
                const baseOption = document.createElement('option');
                baseOption.value = '';
                baseOption.textContent = defaultMunicipioOptionLabel;
                fragment.appendChild(baseOption);

                const normalizedSelectedValue = selectedValueNormalized ? String(selectedValueNormalized).toUpperCase() : '';
                const normalizedSelectedDisplay = selectedDisplay ? normalizeMunicipioValue(selectedDisplay) : '';

                let selectionApplied = false;

                (Array.isArray(lista) ? lista : [])
                    .filter(function (item) {
                        return item && item.nome;
                    })
                    .sort(function (a, b) {
                        return a.nome.localeCompare(b.nome, 'pt-BR');
                    })
                    .forEach(function (item) {
                        const displayName = item.nome;
                        const normalizedName = normalizeMunicipioValue(displayName);
                        const option = document.createElement('option');
                        option.value = normalizedName;
                        option.textContent = displayName;
                        option.dataset.display = displayName;

                        if (!selectionApplied && normalizedSelectedValue && normalizedName === normalizedSelectedValue) {
                            option.selected = true;
                            selectionApplied = true;
                        } else if (!selectionApplied && !normalizedSelectedValue && normalizedSelectedDisplay && normalizedName === normalizedSelectedDisplay) {
                            option.selected = true;
                            selectionApplied = true;
                        }

                        fragment.appendChild(option);
                    });

                if (!selectionApplied && normalizedSelectedValue) {
                    const fallbackOption = document.createElement('option');
                    fallbackOption.value = normalizedSelectedValue;
                    fallbackOption.textContent = selectedDisplay || normalizedSelectedValue;
                    fallbackOption.dataset.display = selectedDisplay || normalizedSelectedValue;
                    fallbackOption.selected = true;
                    fragment.appendChild(fallbackOption);
                }

                municipioSelect.innerHTML = '';
                municipioSelect.appendChild(fragment);
                municipioSelect.disabled = false;
                syncMunicipioDisplay();
            };

            const loadMunicipios = async function (uf, selectedValue, selectedDisplay) {
                if (!municipioSelect) {
                    return;
                }

                const resolvedUf = typeof uf === 'string' ? uf.trim().toUpperCase() : '';
                const normalizedSelectedValue = selectedValue ? normalizeMunicipioValue(selectedValue) : '';
                const selectedDisplayValue = selectedDisplay ? String(selectedDisplay) : '';

                if (!resolvedUf) {
                    resetMunicipioSelect(true);
                    return;
                }

                if (resolvedUf === lastMunicipioUf && municipioSelect.dataset.loadedUf === resolvedUf) {
                    municipioSelect.disabled = false;
                    if (normalizedSelectedValue) {
                        municipioSelect.value = normalizedSelectedValue;
                    } else {
                        municipioSelect.value = '';
                    }
                    syncMunicipioDisplay();
                    return;
                }

                municipioRequestId += 1;
                const currentId = municipioRequestId;

                municipioSelect.disabled = true;
                municipioSelect.classList.add('opacity-60');
                if (municipioDisplayInput) {
                    municipioDisplayInput.value = '';
                }

                try {
                    const response = await fetch('api/municipios.php?uf=' + encodeURIComponent(resolvedUf));
                    if (!response.ok) {
                        throw new Error('Resposta HTTP invalida');
                    }

                    const lista = await response.json();
                    if (currentId !== municipioRequestId) {
                        return;
                    }

                    lastMunicipioUf = resolvedUf;
                    populateMunicipios(lista, normalizedSelectedValue, selectedDisplayValue);
                    municipioSelect.dataset.loadedUf = resolvedUf;
                } catch (error) {
                    console.error('Falha ao carregar municipios', error);
                    resetMunicipioSelect(false);
                    if (normalizedSelectedValue) {
                        const fallbackOption = document.createElement('option');
                        fallbackOption.value = normalizedSelectedValue;
                        fallbackOption.textContent = selectedDisplayValue || normalizedSelectedValue;
                        fallbackOption.dataset.display = selectedDisplayValue || normalizedSelectedValue;
                        fallbackOption.selected = true;
                        municipioSelect.appendChild(fallbackOption);
                    }
                    syncMunicipioDisplay();
                } finally {
                    municipioSelect.classList.remove('opacity-60');
                }
            };

            if (municipioSelect) {
                const initialShouldDisable = !(ufSelect && ufSelect.value);
                resetMunicipioSelect(initialShouldDisable);

                if (ufSelect && ufSelect.value) {
                    const initialMunicipioValue = municipioSelect.dataset.initialMunicipio || '';
                    const initialMunicipioDisplay = municipioSelect.dataset.initialDisplay || '';
                    loadMunicipios(ufSelect.value, initialMunicipioValue, initialMunicipioDisplay).finally(function () {
                        municipioSelect.dataset.initialMunicipio = '';
                        municipioSelect.dataset.initialDisplay = '';
                    });
                }
            }

            if (ufSelect && municipioSelect) {
                ufSelect.addEventListener('change', function () {
                    municipioSelect.dataset.initialMunicipio = '';
                    municipioSelect.dataset.initialDisplay = '';
                    loadMunicipios(ufSelect.value, '', '');
                });
            }

            if (municipioSelect) {
                municipioSelect.addEventListener('change', function () {
                    syncMunicipioDisplay();
                });
            }

            if (searchForm && municipioSelect) {
                searchForm.addEventListener('submit', function () {
                    syncMunicipioDisplay();
                });
            }

            if (cnaeInput) {
                cnaeInput.addEventListener('input', function () {
                    const digits = this.value.replace(/\D/g, '').slice(0, 7);
                    if (this.value !== digits) {
                        this.value = digits;
                    }
                });
            }

            const historyButtons = document.querySelectorAll('.history-apply');
            historyButtons.forEach(function (button) {
                button.addEventListener('click', async function () {
                    if (!searchForm) {
                        return;
                    }

                    const raw = button.getAttribute('data-filtros') || '{}';
                    let filtros;

                    try {
                        filtros = JSON.parse(raw);
                    } catch (error) {
                        console.error('Erro ao processar filtros do historico', error);
                        return;
                    }

                    if (typeof filtros !== 'object' || filtros === null) {
                        return;
                    }

                    const setFieldValue = function (name, value) {
                        const field = searchForm.querySelector('[name=\"' + name + '\"]');
                        if (!field) {
                            return;
                        }

                        let resolved = '';
                        if (Array.isArray(value)) {
                            resolved = value.join(', ');
                        } else if (value !== undefined && value !== null) {
                            resolved = String(value);
                        }
                        field.value = resolved;
                    };

                    const setCheckbox = function (name, flag) {
                        const checkbox = searchForm.querySelector('input[name=\"' + name + '\"]');
                        if (checkbox) {
                            checkbox.checked = Boolean(flag);
                        }
                    };

                    const applySituacao = function (value) {
                        const select = document.getElementById('search-situacao');
                        if (!select) {
                            return;
                        }

                        let resolved = Array.isArray(value) ? value[0] : value;
                        resolved = resolved ? String(resolved).toUpperCase() : 'ATIVA';

                        const matchingOption = Array.from(select.options).find(function (option) {
                            return option.value === resolved;
                        });

                        if (matchingOption) {
                            select.value = matchingOption.value;
                        } else if (select.options.length > 0) {
                            select.selectedIndex = 0;
                        }
                    };

                    const ufValor = Array.isArray(filtros.uf) ? (filtros.uf[0] ?? '') : (filtros.uf ?? '');
                    const municipioValor = Array.isArray(filtros.municipio) ? (filtros.municipio[0] ?? '') : (filtros.municipio ?? '');
                    const municipioDisplayValor = filtros.municipio_display ?? '';
                    const ncmValor = filtros.ncm_display ?? filtros.ncm;

                    setFieldValue('cnae', filtros.cnae);
                    setFieldValue('ncm', ncmValor);
                    setFieldValue('uf', ufValor);
                    setFieldValue('municipio_display', municipioDisplayValor);
                    await loadMunicipios(ufValor, municipioValor, municipioDisplayValor);
                    if (municipioDisplayInput && municipioDisplayValor && municipioDisplayInput.value === '') {
                        municipioDisplayInput.value = municipioDisplayValor;
                    }
                    setFieldValue('quantidade', filtros.quantidade);
                    setFieldValue('pagina', filtros.pagina);
                    setFieldValue('capital_social_minimo', filtros.capital_social_minimo);
                    setFieldValue('capital_social_maximo', filtros.capital_social_maximo);
                    setFieldValue('codigo_atividade_secundaria', filtros.codigo_atividade_secundaria);
                    setFieldValue('codigo_natureza_juridica', filtros.codigo_natureza_juridica);
                    setFieldValue('cep', filtros.cep);
                    setFieldValue('cnpj', filtros.cnpj);
                    setFieldValue('ddd', filtros.ddd);
                    setFieldValue('data_abertura_inicio', filtros.data_abertura_inicio);
                    setFieldValue('data_abertura_fim', filtros.data_abertura_fim);
                    setFieldValue('data_abertura_ultimos_dias', filtros.data_abertura_ultimos_dias);
                    setFieldValue('mei', filtros.mei);
                    setFieldValue('simples', filtros.simples);

                    setCheckbox('mei_excluir', filtros.mei_excluir);
                    setCheckbox('simples_excluir', filtros.simples_excluir);
                    setCheckbox('somente_celular', filtros.somente_celular);
                    setCheckbox('somente_fixo', filtros.somente_fixo);
                    setCheckbox('somente_matriz', filtros.somente_matriz);
                    setCheckbox('somente_filial', filtros.somente_filial);
                    setCheckbox('com_email', filtros.com_email);
                    setCheckbox('com_telefone', filtros.com_telefone);
                    setCheckbox('excluir_email_contab', filtros.excluir_email_contab);
                    setCheckbox('excluir_empresas_visualizadas', filtros.excluir_empresas_visualizadas);
                    setCheckbox('incluir_atividade_secundaria', filtros.incluir_atividade_secundaria);

                    const situacaoValor = filtros.situacao !== undefined ? filtros.situacao : filtros.situacao_cadastral;
                    applySituacao(situacaoValor);

                    if (!municipioValor && municipioSelect) {
                        municipioSelect.value = '';
                        syncMunicipioDisplay();
                    }

                    if (typeof searchForm.requestSubmit === 'function') {
                        searchForm.requestSubmit();
                    } else {
                        searchForm.submit();
                    }
                });
            });

            const leadLinks = document.querySelectorAll('.lead-view-link');
            leadLinks.forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    const proceed = confirm('Visualizar os detalhes deste lead consumira 1 credito. Deseja continuar?');
                    if (proceed) {
                        window.location.href = link.href;
                    }
                });
            });

            document.querySelectorAll('.single-add-form').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    const confirmation = confirm('Adicionar este lead a lista consumira 1 credito. Deseja continuar?');
                    if (!confirmation) {
                        event.preventDefault();
                    }
                });
            });

            const bulkForm = document.getElementById('bulk-add-form');
            if (bulkForm) {
                const checkboxes = document.querySelectorAll('.bulk-lead-checkbox');
                const hiddenContainer = document.getElementById('bulk-hidden-container');
                const submitBtn = document.getElementById('bulk-submit-btn');
                const selectAll = document.getElementById('bulk-select-all');
                const listaSelect = document.getElementById('bulk-lista-select');
                const countDisplay = document.getElementById('bulk-count-display');

                const updateHidden = function () {
                    if (hiddenContainer) {
                        hiddenContainer.innerHTML = '';
                    }

                    let selected = 0;

                    checkboxes.forEach(function (cb) {
                        if (cb.checked) {
                            selected += 1;
                            if (hiddenContainer) {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'lead_tokens[]';
                                input.value = cb.value;
                                hiddenContainer.appendChild(input);
                            }
                        }
                    });

                    bulkForm.dataset.selectedCount = String(selected);

                    if (countDisplay) {
                        countDisplay.textContent = selected;
                    }

                    const listaSelecionada = listaSelect && listaSelect.value !== '';
                    const habilitado = selected > 0 && listaSelecionada;

                    if (submitBtn) {
                        submitBtn.disabled = !habilitado;
                        submitBtn.classList.toggle('opacity-50', !habilitado);
                        submitBtn.classList.toggle('cursor-not-allowed', !habilitado);
                    }
                };

                checkboxes.forEach(function (cb) {
                    cb.addEventListener('change', updateHidden);
                });

                if (selectAll) {
                    selectAll.addEventListener('change', function () {
                        const marcado = selectAll.checked;
                        checkboxes.forEach(function (cb) {
                            cb.checked = marcado;
                        });
                        updateHidden();
                    });
                }

                if (listaSelect) {
                    listaSelect.addEventListener('change', updateHidden);
                }

                bulkForm.addEventListener('submit', function (event) {
                    updateHidden();

                    if (submitBtn && submitBtn.disabled) {
                        event.preventDefault();
                        return;
                    }

                    const selecionados = parseInt(bulkForm.dataset.selectedCount || '0', 10);
                    if (selecionados > 0) {
                        const mensagem = 'Adicionar ' + selecionados + ' lead' + (selecionados > 1 ? 's' : '') + ' consumira ' + selecionados + ' credito' + (selecionados > 1 ? 's' : '') + '. Deseja continuar?';
                        if (!confirm(mensagem)) {
                            event.preventDefault();
                        }
                    }
                });

                updateHidden();
            }

            const NCM_MIN_QUERY_LENGTH = 2;
            const NCM_LIMIT = 25;
            const ncmForm = document.getElementById('ncm-search-form');
            const ncmInput = document.getElementById('ncm-query');
            const ncmStatus = document.getElementById('ncm-status');
            const ncmResults = document.getElementById('ncm-results');
            let ncmRequestId = 0;
            let ncmDebounceHandle = null;

            const setNcmStatus = function (message, isError) {
                if (!ncmStatus) {
                    return;
                }
                ncmStatus.textContent = message;
                if (isError) {
                    ncmStatus.classList.add('text-red-600');
                    ncmStatus.classList.remove('text-slate-500');
                } else {
                    ncmStatus.classList.remove('text-red-600');
                    ncmStatus.classList.add('text-slate-500');
                }
            };

            const renderNcmResults = function (items) {
                if (!ncmResults) {
                    return;
                }

                if (!Array.isArray(items) || items.length === 0) {
                    ncmResults.innerHTML = '<p class="text-sm text-slate-500">Nenhum NCM encontrado para os termos informados.</p>';
                    return;
                }

                const rows = items.map(function (item) {
                    const code = item && item.codigo ? String(item.codigo) : '-';
                    const description = item && item.descricao ? String(item.descricao) : '-';
                    const start = item && item.data_inicio ? String(item.data_inicio) : '-';
                    const end = item && item.data_fim ? String(item.data_fim) : '-';
                    const vigencia = (start !== '-' || end !== '-') ? (start + ' a ' + end) : '-';

                    return (
                        '<tr class="border-b border-slate-100">' +
                            '<td class="px-4 py-2 font-mono text-sm text-slate-700">' + escapeHtml(code) + '</td>' +
                            '<td class="px-4 py-2 text-sm text-slate-600">' + escapeHtml(description) + '</td>' +
                            '<td class="px-4 py-2 text-xs text-slate-500">' + escapeHtml(vigencia) + '</td>' +
                            '<td class="px-4 py-2 text-right">' +
                                '<button type="button" class="ncm-add-btn text-blue-600 hover:text-blue-700 text-xs font-medium" data-ncm-code="' + escapeHtml(code) + '" data-ncm-description="' + escapeHtml(description) + '">Adicionar ao filtro</button>' +
                            '</td>' +
                        '</tr>'
                    );
                }).join('');

                const table = '' +
                    '<table class="min-w-full text-sm">' +
                        '<thead>' +
                            '<tr class="bg-blue-50 text-blue-700 text-left">' +
                                '<th class="px-4 py-2 font-medium">Codigo</th>' +
                                '<th class="px-4 py-2 font-medium">Descricao</th>' +
                                '<th class="px-4 py-2 font-medium">Vigencia</th>' +
                                '<th class="px-4 py-2 font-medium text-right">Acoes</th>' +
                            '</tr>' +
                        '</thead>' +
                        '<tbody>' + rows + '</tbody>' +
                    '</table>';

                ncmResults.innerHTML = table;
            };

            const fetchNcm = async function (query) {
                ncmRequestId += 1;
                const currentRequest = ncmRequestId;

                setNcmStatus('Carregando resultados...', false);
                if (ncmResults) {
                    ncmResults.innerHTML = '<p class="text-sm text-slate-500">Consultando base de NCM...</p>';
                }

                try {
                    const params = new URLSearchParams();
                    params.set('limit', String(NCM_LIMIT));
                    if (query) {
                        params.set('q', query);
                    }

                    const response = await fetch('api/ncm.php?' + params.toString());
                    if (currentRequest !== ncmRequestId) {
                        return;
                    }
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    const payload = await response.json();
                    if (currentRequest !== ncmRequestId) {
                        return;
                    }

                    const items = Array.isArray(payload) ? payload : [];
                    renderNcmResults(items);

                    if (items.length > 0) {
                        setNcmStatus('Mostrando ' + items.length + ' resultado' + (items.length > 1 ? 's' : '') + '.', false);
                    } else {
                        setNcmStatus('Nenhum NCM encontrado para "' + query + '".', false);
                    }
                } catch (error) {
                    if (currentRequest !== ncmRequestId) {
                        return;
                    }
                    console.error('Erro ao consultar NCM', error);
                    setNcmStatus('Nao foi possivel carregar os NCMs. Tente novamente.', true);
                    if (ncmResults) {
                        ncmResults.innerHTML = '<p class="text-sm text-red-600">Erro ao carregar os dados no momento.</p>';
                    }
                }
            };

            const performNcmSearch = function (rawQuery) {
                const query = (rawQuery || '').trim();
                if (query === '') {
                    setNcmStatus('Digite um termo para pesquisar. Resultados limitados aos 25 primeiros itens.', false);
                    if (ncmResults) {
                        ncmResults.innerHTML = '<p class="text-sm text-slate-500">Informe um termo com pelo menos ' + NCM_MIN_QUERY_LENGTH + ' caracteres.</p>';
                    }
                    return;
                }

                if (query.length < NCM_MIN_QUERY_LENGTH) {
                    setNcmStatus('Digite pelo menos ' + NCM_MIN_QUERY_LENGTH + ' caracteres para pesquisar.', false);
                    if (ncmResults) {
                        ncmResults.innerHTML = '<p class="text-sm text-slate-500">Termo muito curto para pesquisar.</p>';
                    }
                    return;
                }

                fetchNcm(query);
            };

            const scheduleNcmSearch = function () {
                if (!ncmInput) {
                    return;
                }
                const value = ncmInput.value || '';
                if (ncmDebounceHandle) {
                    clearTimeout(ncmDebounceHandle);
                }
                ncmDebounceHandle = setTimeout(function () {
                    performNcmSearch(value);
                }, 400);
            };

            if (ncmForm && ncmInput) {
                ncmForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    performNcmSearch(ncmInput.value || '');
                });

                ncmInput.addEventListener('input', scheduleNcmSearch);
            }

            if (ncmResults) {
                ncmResults.addEventListener('click', function (event) {
                    const button = event.target ? event.target.closest('.ncm-add-btn') : null;
                    if (!button) {
                        return;
                    }

                    const code = (button.getAttribute('data-ncm-code') || '').trim();
                    if (!code) {
                        setNcmStatus('Codigo NCM nao disponivel.', true);
                        return;
                    }

                    if (ncmFilterField) {
                        const existingList = ncmFilterField.value
                            ? ncmFilterField.value.split(/[,;\s]+/).map(function (item) { return item.trim(); }).filter(Boolean)
                            : [];
                        if (!existingList.includes(code)) {
                            existingList.push(code);
                        }
                        ncmFilterField.value = existingList.join(', ');
                    }

                    setNcmStatus('Codigo ' + code + ' adicionado ao campo NCM.', false);

                    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        navigator.clipboard.writeText(code).catch(function (error) {
                            console.warn('Nao foi possivel copiar automaticamente o NCM', error);
                        });
                    }
                });
            }
        });
    </script>
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)): ?>
    <div class="bg-white rounded-xl shadow p-6">
        <p class="text-sm text-slate-500">Nenhum lead encontrado para os filtros informados.</p>
    </div>
<?php endif; ?>
<?php
renderPageEnd();













