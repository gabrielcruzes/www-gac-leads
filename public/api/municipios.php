<?php
/**
 * public/api/municipios.php
 *
 * Proxy simples para listar municipios de uma UF utilizando a BrazilAPI.
 * A rota tenta primeiro o endpoint oficial /api/v1/cities/:state_id; se o provedor
 * retornar erro (ainda comum em algumas regioes), fazemos fallback para
 * /api/ibge/municipios/v1/:uf, tambem disponibilizado pela BrazilAPI.
 * As respostas sao cacheadas em disco para reduzir latencia e rate limiting.
 */

$uf = strtoupper(trim($_GET['uf'] ?? ''));

if (!preg_match('/^[A-Z]{2}$/', $uf)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'UF invalida. Utilize exatamente 2 letras.']);
    exit;
}

$projectRoot = dirname(__DIR__, 2);
$cacheDir = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
$cacheTtl = 86400; // 24 horas
$statesCacheTtl = 604800; // 7 dias

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

$municipioCacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'municipios-' . $uf . '.json';
if (is_file($municipioCacheFile) && (time() - filemtime($municipioCacheFile) < $cacheTtl)) {
    $cached = @file_get_contents($municipioCacheFile);
    if ($cached !== false) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $cached;
        exit;
    }
}

/**
 * Executa uma requisicao GET retornando array com status e corpo decodificado.
 *
 * @return array{status:int,body:mixed}|null
 */
function brasilApiGet(string $url): ?array
{
    $headers = [
        'Accept: application/json',
        'User-Agent: GAC-Leads/1.0',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            curl_close($ch);
            return null;
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    } else {
        global $http_response_header;

        $http_response_header = [];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => implode("\r\n", $headers) . "\r\n",
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
    }

    $decoded = json_decode($body, true);

    return [
        'status' => $status,
        'body' => $decoded,
    ];
}

function loadStateIdMap(string $cacheDir, int $ttl): array
{
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'brazilapi-uf-map.json';

    if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }

    $result = brasilApiGet('https://brasilapi.com.br/api/ibge/uf/v1');
    if ($result === null || $result['status'] >= 400 || !is_array($result['body'])) {
        return [];
    }

    $map = [];
    foreach ($result['body'] as $state) {
        if (!isset($state['sigla'], $state['id'])) {
            continue;
        }
        $map[strtoupper($state['sigla'])] = (int) $state['id'];
    }

    @file_put_contents($cacheFile, json_encode($map, JSON_UNESCAPED_UNICODE));

    return $map;
}

$stateIdMap = loadStateIdMap($cacheDir, $statesCacheTtl);
$stateId = $stateIdMap[$uf] ?? null;

$cities = null;

if ($stateId !== null) {
    $citiesResponse = brasilApiGet('https://brasilapi.com.br/api/v1/cities/' . rawurlencode((string) $stateId));
    if ($citiesResponse !== null && $citiesResponse['status'] < 400 && is_array($citiesResponse['body'])) {
        $payload = $citiesResponse['body'];
        $normalizedFromCities = [];

        foreach ($payload as $cityEntry) {
            if (is_string($cityEntry)) {
                $normalizedFromCities[] = [
                    'id' => null,
                    'nome' => $cityEntry,
                ];
                continue;
            }

            if (is_array($cityEntry)) {
                $name = $cityEntry['name'] ?? $cityEntry['nome'] ?? null;
                if ($name === null) {
                    continue;
                }
                $normalizedFromCities[] = [
                    'id' => $cityEntry['id'] ?? ($cityEntry['codigo_ibge'] ?? null),
                    'nome' => $name,
                ];
            }
        }

        if (!empty($normalizedFromCities)) {
            $cities = $normalizedFromCities;
        }
    }
}

if ($cities === null) {
    $fallbackResponse = brasilApiGet('https://brasilapi.com.br/api/ibge/municipios/v1/' . rawurlencode($uf));
    if ($fallbackResponse === null || $fallbackResponse['status'] >= 400 || !is_array($fallbackResponse['body'])) {
        http_response_code(502);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Nao foi possivel consultar os municipios no momento.']);
        exit;
    }

    $cities = array_map(static function ($entry) {
        $name = null;
        if (is_array($entry)) {
            $name = $entry['nome'] ?? $entry['name'] ?? null;
        } elseif (is_string($entry)) {
            $name = $entry;
        }

        return [
            'id' => is_array($entry) ? ($entry['codigo_ibge'] ?? ($entry['id'] ?? null)) : null,
            'nome' => $name,
        ];
    }, $fallbackResponse['body']);
}

$cities = array_values(array_filter($cities, static function ($entry) {
    return isset($entry['nome']) && $entry['nome'] !== null && $entry['nome'] !== '';
}));

usort($cities, static function ($a, $b) {
    return strcmp($a['nome'], $b['nome']);
});

$jsonPayload = json_encode($cities, JSON_UNESCAPED_UNICODE);
if ($jsonPayload === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Falha ao codificar resposta.']);
    exit;
}

@file_put_contents($municipioCacheFile, $jsonPayload);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo $jsonPayload;
