<?php
/**
 * public/api/municipios.php
 *
 * Proxy simples para listar municipios de uma UF via API do IBGE.
 * Retorna JSON normalizado e utiliza cache em disco para reduzir chamadas externas.
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

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'municipios-' . $uf . '.json';
$cacheValid = false;

if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    $cacheValid = true;
}

if ($cacheValid) {
    $payload = @file_get_contents($cacheFile);
    if ($payload !== false) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $payload;
        exit;
    }
}

$remoteUrl = sprintf(
    'https://servicodados.ibge.gov.br/api/v1/localidades/estados/%s/municipios',
    rawurlencode($uf)
);

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 8,
        'header' => "Accept: application/json\r\nUser-Agent: GAC-Leads/1.0\r\n",
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
]);

$responseBody = @file_get_contents($remoteUrl, false, $context);

if ($responseBody === false) {
    if ($cacheValid) {
        $payload = @file_get_contents($cacheFile);
        if ($payload !== false) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: public, max-age=600');
            echo $payload;
            exit;
        }
    }

    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Nao foi possivel consultar os municipios no momento.']);
    exit;
}

$decoded = json_decode($responseBody, true);
if (!is_array($decoded)) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Resposta inesperada da API do IBGE.']);
    exit;
}

$normalized = array_map(static function ($entry) {
    return [
        'id' => $entry['id'] ?? null,
        'nome' => $entry['nome'] ?? null,
    ];
}, $decoded);

$jsonPayload = json_encode($normalized, JSON_UNESCAPED_UNICODE);
if ($jsonPayload === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Falha ao codificar resposta.']);
    exit;
}

@file_put_contents($cacheFile, $jsonPayload);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo $jsonPayload;
