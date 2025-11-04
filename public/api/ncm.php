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

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => "Accept: application/json\r\nUser-Agent: GAC-Leads/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $remoteUrl = 'https://brasilapi.com.br/api/ncm/v1';
    $response = @file_get_contents($remoteUrl, false, $context);
    if ($response === false) {
        return [];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [];
    }

    @file_put_contents($cacheFile, json_encode($decoded, JSON_UNESCAPED_UNICODE));

    return $decoded;
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
