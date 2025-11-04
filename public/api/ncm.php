<?php
/**
 * public/api/ncm.php
 *
 * Proxy para consulta de codigos NCM utilizando a BrazilAPI.
 * Carrega o catalogo completo, armazena em cache e permite filtro por descricao ou codigo.
 */

$projectRoot = dirname(__DIR__, 2);
$cacheDir = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
$cacheTtl = 604800; // 7 dias

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'ncm-catalog.json';

/**
 * Normaliza strings removendo acentos e caracteres especiais para comparacoes.
 */
function normalizeString(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $trimmed);
    if ($converted === false || $converted === null) {
        $converted = $trimmed;
    }

    $lower = strtolower($converted);
    $sanitized = preg_replace('/[^a-z0-9\s\.]/', ' ', $lower);
    if ($sanitized === null) {
        $sanitized = $lower;
    }

    $sanitized = str_replace('.', ' ', $sanitized);

    $condensed = preg_replace('/\s+/', ' ', $sanitized);
    if ($condensed === null) {
        $condensed = $sanitized;
    }

    return trim($condensed);
}

/**
 * Executa chamada HTTP para a BrazilAPI retornando array decodificado.
 *
 * @return array<int,array<string,mixed>>|null
 */
function fetchBrazilApiCatalog(string $url): ?array
{
    $headers = [
        'Accept: application/json',
        'User-Agent: GAC-Leads/1.0',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            curl_close($ch);
            return null;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($status >= 400) {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    global $http_response_header;

    $http_response_header = [];
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'header' => implode("\r\n", $headers) . "\r\n",
            'protocol_version' => 1.1,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return null;
    }

    $statusLine = $http_response_header[0] ?? 'HTTP/1.1 500';
    if (!preg_match('/\s(\d{3})\s/', $statusLine, $statusMatch)) {
        return null;
    }
    $status = (int) $statusMatch[1];
    if ($status >= 400) {
        return null;
    }

    $decoded = json_decode($body, true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * Catálogo minimo para fallback quando a BrazilAPI estiver indisponivel.
 *
 * @return array<int,array<string,string>>
 */
function ncmFallbackCatalog(): array
{
    return [
        [
            'codigo' => '3305.10.00',
            'descricao' => '- Xampus',
            'data_inicio' => '2022-04-01',
            'data_fim' => '9999-12-31',
            'tipo_ato' => 'Res Camex',
            'numero_ato' => '000272',
            'ano_ato' => '2021',
        ],
        [
            'codigo' => '3304.99.90',
            'descricao' => '- Outros produtos de beleza ou de maquiagem preparados',
            'data_inicio' => '2022-04-01',
            'data_fim' => '9999-12-31',
            'tipo_ato' => 'Res Camex',
            'numero_ato' => '000272',
            'ano_ato' => '2021',
        ],
        [
            'codigo' => '2106.90.10',
            'descricao' => '- Suplementos alimentares',
            'data_inicio' => '2022-04-01',
            'data_fim' => '9999-12-31',
            'tipo_ato' => 'Res Camex',
            'numero_ato' => '000272',
            'ano_ato' => '2021',
        ],
        [
            'codigo' => '8504.40.40',
            'descricao' => '- Carregadores de baterias para telefones moveis',
            'data_inicio' => '2022-04-01',
            'data_fim' => '9999-12-31',
            'tipo_ato' => 'Res Camex',
            'numero_ato' => '000272',
            'ano_ato' => '2021',
        ],
    ];
}

/**
 * Carrega o catalogo de NCM da BrazilAPI (com cache local).
 *
 * @return array<int,array<string,mixed>>
 */
function loadNcmCatalog(string $cacheFile, int $cacheTtl): array
{
    if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }

    $catalog = fetchBrazilApiCatalog('https://brasilapi.com.br/api/ncm/v1');
    if ($catalog === null || !is_array($catalog) || count($catalog) === 0) {
        $fallback = ncmFallbackCatalog();
        if (!empty($fallback)) {
            @file_put_contents($cacheFile, json_encode($fallback, JSON_UNESCAPED_UNICODE));
            return $fallback;
        }
        return [];
    }

    @file_put_contents($cacheFile, json_encode($catalog, JSON_UNESCAPED_UNICODE));

    return $catalog;
}

$catalog = loadNcmCatalog($cacheFile, $cacheTtl);

if (empty($catalog)) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Nao foi possivel carregar a base de NCM.']);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 25);
if ($limit < 1) {
    $limit = 1;
} elseif ($limit > 200) {
    $limit = 200;
}

$normalizedQuery = normalizeString($query);

$results = [];

if ($normalizedQuery === '') {
    $results = array_slice($catalog, 0, $limit);
} else {
    foreach ($catalog as $entry) {
        if (!isset($entry['codigo']) || !isset($entry['descricao'])) {
            continue;
        }

        $normalizedCode = normalizeString((string) $entry['codigo']);
        $normalizedDescription = normalizeString((string) $entry['descricao']);

        if ($normalizedCode === '' && $normalizedDescription === '') {
            continue;
        }

        if (strpos($normalizedCode, $normalizedQuery) !== false || strpos($normalizedDescription, $normalizedQuery) !== false) {
            $results[] = $entry;
        }

        if (count($results) >= $limit) {
            break;
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo json_encode(array_values($results), JSON_UNESCAPED_UNICODE);
