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

const BRAZIL_STATE_ID_MAP = [
    'RO' => 11,
    'AC' => 12,
    'AM' => 13,
    'RR' => 14,
    'PA' => 15,
    'AP' => 16,
    'TO' => 17,
    'MA' => 21,
    'PI' => 22,
    'CE' => 23,
    'RN' => 24,
    'PB' => 25,
    'PE' => 26,
    'AL' => 27,
    'SE' => 28,
    'BA' => 29,
    'MG' => 31,
    'ES' => 32,
    'RJ' => 33,
    'SP' => 35,
    'PR' => 41,
    'SC' => 42,
    'RS' => 43,
    'MS' => 50,
    'MT' => 51,
    'GO' => 52,
    'DF' => 53,
];

$rawUf = trim((string) ($_GET['uf'] ?? ''));
$stateId = null;

if ($rawUf === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'UF invalida. Informe uma sigla (ex.: SP) ou codigo IBGE (ex.: 35).']);
    exit;
}

if (ctype_digit($rawUf)) {
    $stateIdFromRequest = (int) $rawUf;
    $ufFromCode = array_search($stateIdFromRequest, BRAZIL_STATE_ID_MAP, true);
    if ($ufFromCode === false) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Codigo de UF invalido.']);
        exit;
    }
    $uf = $ufFromCode;
    $stateId = $stateIdFromRequest;
} else {
    $uf = strtoupper($rawUf);
    if (!preg_match('/^[A-Z]{2}$/', $uf)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'UF invalida. Informe uma sigla (ex.: SP) ou codigo IBGE (ex.: 35).']);
        exit;
    }
}

if (!is_string($uf) || $uf === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'UF invalida.']);
    exit;
}

$projectRoot = dirname(__DIR__, 2);
$cacheDir = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
$cacheTtl = 600; // 24 horas
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
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
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
        if (!preg_match('/\\s(\\d{3})\\s/', $statusLine, $statusMatch)) {
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

/**
 * Retorna um conjunto minimo de municipios para uso offline por UF.
 *
 * @return array<int,array<string,string|null>>
 */
function municipioFallbackList(string $uf): array
{
    static $fallback = [
        'AC' => ['Rio Branco', 'Cruzeiro do Sul', 'Sena Madureira'],
        'AL' => ['Maceio', 'Arapiraca', 'Palmeira dos Indios'],
        'AP' => ['Macapa', 'Santana', 'Laranjal do Jari'],
        'AM' => ['Manaus', 'Parintins', 'Itacoatiara'],
        'BA' => ['Salvador', 'Feira de Santana', 'Vitoria da Conquista'],
        'CE' => ['Fortaleza', 'Juazeiro do Norte', 'Sobral'],
        'DF' => ['Brasilia', 'Ceilandia', 'Taguatinga'],
        'ES' => ['Vitoria', 'Vila Velha', 'Serra'],
        'GO' => ['Goiania', 'Anapolis', 'Aparecida de Goiania'],
        'MA' => ['Sao Luis', 'Imperatriz', 'Caxias'],
        'MT' => ['Cuiaba', 'Varzea Grande', 'Rondonopolis'],
        'MS' => ['Campo Grande', 'Dourados', 'Tres Lagoas'],
        'MG' => ['Belo Horizonte', 'Uberlandia', 'Contagem'],
        'PA' => ['Belem', 'Ananindeua', 'Santarem'],
        'PB' => ['Joao Pessoa', 'Campina Grande', 'Patos'],
        'PR' => ['Curitiba', 'Londrina', 'Maringa'],
        'PE' => ['Recife', 'Olinda', 'Caruaru'],
        'PI' => ['Teresina', 'Parnaiba', 'Picos'],
        'RJ' => ['Rio de Janeiro', 'Niteroi', 'Campos dos Goytacazes'],
        'RN' => ['Natal', 'Mossoro', 'Parnamirim'],
        'RS' => ['Porto Alegre', 'Caxias do Sul', 'Pelotas'],
        'RO' => ['Porto Velho', 'Ji-Parana', 'Ariquemes'],
        'RR' => ['Boa Vista', 'Rorainopolis', 'Caracarai'],
        'SC' => ['Florianopolis', 'Joinville', 'Blumenau'],
        'SP' => ['Sao Paulo', 'Campinas', 'Santos'],
        'SE' => ['Aracaju', 'Nossa Senhora do Socorro', 'Itabaiana'],
        'TO' => ['Palmas', 'Araguaina', 'Gurupi'],
    ];

    $list = $fallback[$uf] ?? [];

    return array_map(static function (string $name): array {
        return [
            'id' => null,
            'nome' => $name,
        ];
    }, $list);
}

function loadStateIdMap(): array
{
    return BRAZIL_STATE_ID_MAP;
}

/**
 * Recupera municipios diretamente da API oficial do IBGE.
 *
 * @return array<int,array<string,mixed>>|null
 */
function ibgeGetMunicipios(string $uf): ?array
{
    $url = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/' . rawurlencode($uf) . '/municipios';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: GAC-Leads/1.0',
            ],
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
    } else {
        global $http_response_header;

        $http_response_header = [];
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => "Accept: application/json\r\nUser-Agent: GAC-Leads/1.0\r\n",
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
        if ((int) $statusMatch[1] >= 400) {
            return null;
        }
    }

    $decoded = json_decode($body ?? '', true);

    return is_array($decoded) ? $decoded : null;
}

$stateIdMap = loadStateIdMap();
$stateId = $stateId ?? ($stateIdMap[$uf] ?? null);

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
    $ibgeMunicipios = ibgeGetMunicipios($uf);
    if (is_array($ibgeMunicipios) && !empty($ibgeMunicipios)) {
        $cities = array_map(static function ($entry) {
            $name = is_array($entry) ? ($entry['nome'] ?? $entry['name'] ?? null) : (is_string($entry) ? $entry : null);
            return [
                'id' => is_array($entry) ? ($entry['codigo_ibge'] ?? ($entry['id'] ?? null)) : null,
                'nome' => $name,
            ];
        }, $ibgeMunicipios);
    } else {
        $fallbackCities = municipioFallbackList($uf);
        if (!empty($fallbackCities)) {
            $cities = $fallbackCities;
        } else {
            http_response_code(502);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Nao foi possivel consultar os municipios no momento.']);
            exit;
        }
    }
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

